<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Documentation\Application\SpaceAccess;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\Space;

final class DocumentationServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'documentation';
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
            'documentation.space' => Space::class,
            'documentation.page' => Page::class,
        ]);

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('documentation::docs.menu', 'documentation.index', 'documentation.*', fn (User $user): bool => $user->can('documentation.staff.read')
                || Space::query()->tap(fn (Builder $query) => app(SpaceAccess::class)->readable($query, $user))->exists(), order: 35);
        });
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            // Spaces for IT staff (visibility "staff").
            'documentation.staff.read',
            // Every space: create, set readers, editors and owners.
            'documentation.space.manage',
        ]);

        foreach ([DefaultRole::Agent, DefaultRole::TeamLead, DefaultRole::CabMember, DefaultRole::AssetManager, DefaultRole::ContinuityCoordinator, DefaultRole::ApplicationManager, DefaultRole::SecurityOfficer, DefaultRole::RiskManager, DefaultRole::ComplianceManager, DefaultRole::Auditor] as $role) {
            $registry->grantToDefaultRole($role, ['documentation.staff.read']);
        }

        $registry->grantToDefaultRole(DefaultRole::TeamLead, ['documentation.space.manage']);
    }
}
