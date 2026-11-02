<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BiaSettings;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Domain\Models\ImpactCategory;
use Modules\Bia\Domain\Models\TimeHorizon;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Navigation\Navigation;

final class BiaServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'bia';
    }

    public function register(): void
    {
        parent::register();

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'bia.settings' => BiaSettings::class,
            'bia.impact_category' => ImpactCategory::class,
            'bia.time_horizon' => TimeHorizon::class,
            'bia.process' => BusinessProcess::class,
            'bia.assessment' => Assessment::class,
        ]);

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('bia::processes.mine.menu', 'bia.mine', 'bia.mine', fn (User $user): bool => BusinessProcess::query()->ownedBy($user)->exists(), order: 26);
            $navigation->add('bia::processes.menu', 'bia.processes.index', 'bia.processes.*', fn (User $user): bool => $user->can('bia.process.view'), Navigation::RESILIENCE);
        });
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            'bia.process.view',
            'bia.process.manage',
            // Separate from managing: an organization may want approvals by management only.
            'bia.assessment.approve',
            'bia.settings.manage',
        ]);

        // Owner's decision: the coordinator runs the BIA; process owners take part through ownership, not a role.
        $registry->grantToDefaultRole(DefaultRole::BiaCoordinator, ['bia.process.view', 'bia.process.manage', 'bia.assessment.approve', 'bia.settings.manage']);
        // IT reads the business's recovery objectives to plan for them.
        $registry->grantToDefaultRole(DefaultRole::TeamLead, ['bia.process.view']);
    }
}
