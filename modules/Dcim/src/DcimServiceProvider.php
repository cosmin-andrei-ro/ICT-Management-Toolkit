<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Core\Application\Api\ApiAbility;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Links\RackPlacements;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Risks\RiskSignals;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Dcim\Application\Exports\DcimExport;
use Modules\Dcim\Application\Links\MountedAssets;
use Modules\Dcim\Application\Metrics\DcimMetrics;
use Modules\Dcim\Application\Risks\PowerRiskSignals;
use Modules\Dcim\Domain\Models\Cable;
use Modules\Dcim\Domain\Models\IpAddress;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Port;
use Modules\Dcim\Domain\Models\PowerConnection;
use Modules\Dcim\Domain\Models\PowerSource;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Dcim\Domain\Models\Subnet;
use Modules\Dcim\Domain\Models\Vlan;

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
        $this->callAfterResolving(Metrics::class, fn (Metrics $metrics) => $metrics->register(DcimMetrics::source()));
        $this->callAfterResolving(ExportRegistry::class, fn (ExportRegistry $exports) => $exports->register(DcimExport::TYPE, DcimExport::class, 'dcim.infrastructure.view'));
        $this->callAfterResolving(RiskSignals::class, fn (RiskSignals $signals) => $signals->register('dcim', fn (): array => app(PowerRiskSignals::class)()));
    }

    public function boot(): void
    {
        parent::boot();

        ApiAbility::register('dcim:read', 'dcim::api.abilities.read');

        Relation::morphMap([
            'dcim.rack' => Rack::class,
            'dcim.mount' => Mount::class,
            'dcim.port' => Port::class,
            'dcim.cable' => Cable::class,
            'dcim.power_source' => PowerSource::class,
            'dcim.power_connection' => PowerConnection::class,
            'dcim.vlan' => Vlan::class,
            'dcim.subnet' => Subnet::class,
            'dcim.ip_address' => IpAddress::class,
        ]);

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('dcim::racks.menu', 'dcim.racks.index', 'dcim.racks.*', fn (User $user): bool => $user->can('dcim.infrastructure.view'), Navigation::CONFIGURATION);
            $navigation->add('dcim::power.menu', 'dcim.power.index', 'dcim.power.*', fn (User $user): bool => $user->can('dcim.infrastructure.view'), Navigation::CONFIGURATION);
            $navigation->add('dcim::ipam.menu', 'dcim.ipam.index', 'dcim.ipam.*', fn (User $user): bool => $user->can('dcim.infrastructure.view'), Navigation::CONFIGURATION);
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
