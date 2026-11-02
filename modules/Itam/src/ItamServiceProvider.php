<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Events\AssetPlaced;
use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Links\ContractDependents;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Links\PhysicalAssets;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Risks\RiskSignals;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Itam\Application\Exports\AssetExport;
use Modules\Itam\Application\Exports\LicenseExport;
use Modules\Itam\Application\Links\RegisteredAssets;
use Modules\Itam\Application\Listeners\RecordAssetLocation;
use Modules\Itam\Application\Metrics\AssetMetrics;
use Modules\Itam\Application\Risks\AssetRiskSignals;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\AssetAssignment;
use Modules\Itam\Domain\Models\Category;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\LicenseAssignment;
use Modules\Itam\Domain\Models\SoftwareProduct;
use Modules\Itam\Infrastructure\Console\SendLicenseExpiryRemindersCommand;
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

        $this->callAfterResolving(Metrics::class, fn (Metrics $metrics) => $metrics->register(AssetMetrics::source()));

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(RiskSignals::class, fn (RiskSignals $signals) => $signals->register('itam', fn (): array => app(AssetRiskSignals::class)()));
        // The register holds costs and holders: for the people who keep it.
        $this->callAfterResolving(ExportRegistry::class, function (ExportRegistry $exports): void {
            $exports->register(AssetExport::TYPE, AssetExport::class, 'itam.asset.manage');
            $exports->register(LicenseExport::TYPE, LicenseExport::class, 'itam.license.manage');
        });
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
        // The CMDB shows the assets linked to an item; contracts show the assets they cover.
        $this->callAfterResolving(LinkedRecords::class, fn (LinkedRecords $records) => $records->register('itam.asset', self::linkedAssets(...)));
        $this->app->bind(PhysicalAssets::class, RegisteredAssets::class);
        $this->callAfterResolving(ContractDependents::class, function (ContractDependents $dependents): void {
            $dependents->register('itam::assets.covered_by_contract', self::assetsUnderContract(...));
            $dependents->register('itam::licenses.covered_by_contract', self::licensesUnderContract(...));
        });
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(AssetPlaced::class, RecordAssetLocation::class);

        Relation::morphMap([
            'itam.category' => Category::class,
            'itam.asset' => Asset::class,
            'itam.asset_assignment' => AssetAssignment::class,
            'itam.software_product' => SoftwareProduct::class,
            'itam.license' => License::class,
            'itam.license_assignment' => LicenseAssignment::class,
        ]);

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('itam::assets.my.menu', 'itam.my-equipment', 'itam.my-equipment', fn (User $user): bool => Asset::query()->where('assigned_to_id', $user->id)->exists() || LicenseAssignment::query()->where('user_id', $user->id)->exists(), order: 25);
            $navigation->add('itam::assets.menu', 'itam.assets.index', 'itam.assets.*', fn (User $user): bool => $user->can('itam.asset.view'), Navigation::CONFIGURATION);
            $navigation->add('itam::licenses.menu', 'itam.licenses.index', 'itam.licenses.*', fn (User $user): bool => $user->can('itam.license.view'), Navigation::CONFIGURATION);
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendWarrantyRemindersCommand::class)->dailyAt('07:15')->withoutOverlapping()->onOneServer();
            $schedule->command(SendLicenseExpiryRemindersCommand::class)->dailyAt('07:20')->withoutOverlapping()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SendWarrantyRemindersCommand::class, SendLicenseExpiryRemindersCommand::class]);
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

    /**
     * @return list<LinkedRecord>
     */
    private static function licensesUnderContract(Contract $contract, User $viewer): array
    {
        if (! Gate::forUser($viewer)->allows('viewAny', License::class)) {
            return [];
        }

        return array_values(License::query()->with('product')->where('contract_id', $contract->id)->orderBy('name')->limit(100)->get()
            ->map(fn (License $license): LinkedRecord => new LinkedRecord($license->id, $license->product->name.' · '.$license->name, route('itam.licenses.show', $license), $license->expires_on?->toDateString()))
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
            'itam.license.view',
            'itam.license.assign',
            'itam.license.manage',
            // Showing a stored key, after a second-factor code; each showing is audited.
            'itam.license.reveal_key',
        ]);

        $register = ['itam.asset.view', 'itam.asset.assign', 'itam.asset.manage', 'itam.category.manage', 'itam.license.view', 'itam.license.assign', 'itam.license.manage', 'itam.license.reveal_key'];

        $registry->grantToDefaultRole(DefaultRole::Agent, ['itam.asset.view', 'itam.asset.assign', 'itam.license.view', 'itam.license.assign']);
        $registry->grantToDefaultRole(DefaultRole::TeamLead, $register);
        $registry->grantToDefaultRole(DefaultRole::AssetManager, $register);
    }
}
