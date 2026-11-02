<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Links\RackPlacements;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Dcim\Application\Links\MountedAssets;
use Modules\Dcim\Domain\Models\Cable;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Port;
use Modules\Dcim\Domain\Models\Rack;

final class DcimServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'dcim';
    }

    public function register(): void
    {
        parent::register();

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
        $this->app->bind(RackPlacements::class, MountedAssets::class);
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'dcim.rack' => Rack::class,
            'dcim.mount' => Mount::class,
            'dcim.port' => Port::class,
            'dcim.cable' => Cable::class,
        ]);

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('dcim::racks.menu', 'dcim.racks.index', 'dcim.racks.*', fn (User $user): bool => $user->can('dcim.infrastructure.view'), Navigation::CONFIGURATION);
            $navigation->add('dcim::cabling.menu', 'dcim.cables.index', 'dcim.cables.*', fn (User $user): bool => $user->can('dcim.infrastructure.view'), Navigation::CONFIGURATION);
        });
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            // Rooms, racks and what is mounted in them.
            'dcim.infrastructure.view',
            'dcim.infrastructure.manage',
        ]);

        foreach ([DefaultRole::Agent, DefaultRole::TeamLead, DefaultRole::AssetManager, DefaultRole::ContinuityCoordinator] as $role) {
            $registry->grantToDefaultRole($role, ['dcim.infrastructure.view']);
        }

        $registry->grantToDefaultRole(DefaultRole::TeamLead, ['dcim.infrastructure.manage']);
        $registry->grantToDefaultRole(DefaultRole::AssetManager, ['dcim.infrastructure.manage']);
    }
}
