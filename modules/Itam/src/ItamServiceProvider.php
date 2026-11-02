<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Links\ContractDependents;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\AssetAssignment;
use Modules\Itam\Domain\Models\Category;
use Modules\Itam\Infrastructure\Console\SendWarrantyRemindersCommand;

final class ItamServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'itam';
    }

    public function register(): void
    {
        parent::register();

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
        // The CMDB shows the assets linked to an item; contracts show the assets they cover.
        $this->callAfterResolving(LinkedRecords::class, fn (LinkedRecords $records) => $records->register('itam.asset', self::linkedAssets(...)));
        $this->callAfterResolving(ContractDependents::class, fn (ContractDependents $dependents) => $dependents->register('itam::assets.covered_by_contract', self::assetsUnderContract(...)));
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'itam.category' => Category::class,
            'itam.asset' => Asset::class,
            'itam.asset_assignment' => AssetAssignment::class,
        ]);

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('itam::assets.my.menu', 'itam.my-equipment', 'itam.my-equipment', fn (User $user): bool => Asset::query()->where('assigned_to_id', $user->id)->exists(), order: 25);
            $navigation->add('itam::assets.menu', 'itam.assets.index', 'itam.assets.*', fn (User $user): bool => $user->can('itam.asset.view'), Navigation::CONFIGURATION);
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendWarrantyRemindersCommand::class)->dailyAt('07:15')->withoutOverlapping()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SendWarrantyRemindersCommand::class]);
        }
    }

    /**
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    private static function linkedAssets(array $ids, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values(Asset::query()->with('category')->whereKey($ids)->orderBy('asset_tag')->limit(50)->get()
            ->filter(fn (Asset $asset): bool => $gate->allows('view', $asset))
            ->map(fn (Asset $asset): LinkedRecord => self::record($asset))
            ->all());
    }

    /**
     * @return list<LinkedRecord>
     */
    private static function assetsUnderContract(Contract $contract, User $viewer): array
    {
        if (! Gate::forUser($viewer)->allows('viewAny', Asset::class)) {
            return [];
        }

        return array_values(Asset::query()->with('category')->where('contract_id', $contract->id)->orderBy('asset_tag')->limit(100)->get()
            ->map(fn (Asset $asset): LinkedRecord => self::record($asset))
            ->all());
    }

    private static function record(Asset $asset): LinkedRecord
    {
        return new LinkedRecord($asset->id, $asset->asset_tag.' '.$asset->displayName(), route('itam.assets.show', $asset), $asset->status->label());
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            'itam.asset.view',
            // Hand out and take back equipment: the service desk does it every day.
            'itam.asset.assign',
            // The register itself: details, costs, status, disposal.
            'itam.asset.manage',
            'itam.category.manage',
        ]);

        $registry->grantToDefaultRole(DefaultRole::Agent, ['itam.asset.view', 'itam.asset.assign']);
        $registry->grantToDefaultRole(DefaultRole::TeamLead, ['itam.asset.view', 'itam.asset.assign', 'itam.asset.manage', 'itam.category.manage']);
        $registry->grantToDefaultRole(DefaultRole::AssetManager, ['itam.asset.view', 'itam.asset.assign', 'itam.asset.manage', 'itam.category.manage']);
    }
}
