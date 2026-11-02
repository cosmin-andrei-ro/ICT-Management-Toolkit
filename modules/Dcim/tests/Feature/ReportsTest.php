<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Dcim\Application\Actions\AddPorts;
use Modules\Dcim\Application\Actions\ConnectPorts;
use Modules\Dcim\Application\Exports\DcimExport;
use Modules\Dcim\Domain\Enums\CableStatus;
use Modules\Dcim\Domain\Enums\CableType;
use Modules\Dcim\Domain\Enums\MountFace;
use Modules\Dcim\Domain\Enums\PortKind;
use Modules\Dcim\Domain\Models\IpAddress;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\PowerConnection;
use Modules\Dcim\Domain\Models\PowerSource;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Dcim\Domain\Models\Subnet;
use Modules\Dcim\Http\Livewire\RackIndex;
use Modules\Dcim\Tests\Support\DcimScenario;
use Modules\Itam\Domain\Models\Asset;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->agent = DcimScenario::member($this->organization, DefaultRole::Agent);
    $this->room = Location::factory()->create(['kind' => 'room', 'name' => 'Server room']);
    $this->rack = Rack::factory()->create(['location_id' => $this->room->id, 'name' => 'R01', 'height_u' => 10]);

    $this->db = Mount::factory()->create(['rack_id' => $this->rack->id, 'asset_id' => Asset::factory()->create(['name' => 'db-01', 'serial_number' => 'SN-DB01'])->id, 'position_u' => 1, 'height_u' => 2, 'power_watts' => 500]);
    $this->switch = Mount::factory()->create(['rack_id' => $this->rack->id, 'asset_id' => Asset::factory()->create(['name' => 'Switch 1'])->id, 'position_u' => 10, 'power_watts' => 100]);

    $pdu = PowerSource::factory()->create(['location_id' => $this->room->id, 'name' => 'PDU A1', 'capacity_watts' => 550, 'rack_id' => $this->rack->id]);
    PowerConnection::factory()->create(['mount_id' => $this->db->id, 'source_id' => $pdu->id, 'outlet' => '3']);
    PowerConnection::factory()->create(['mount_id' => $this->switch->id, 'source_id' => $pdu->id]);

    app(AddPorts::class)->handle($this->db, 'eth0', PortKind::Rj45, MountFace::Rear, false);
    app(AddPorts::class)->handle($this->switch, 'Gi1', PortKind::Rj45, MountFace::Front, false);
    app(ConnectPorts::class)->connect($this->db->ports()->sole(), $this->switch->ports()->sole(), ['label' => 'C-001', 'type' => CableType::Cat6a, 'colour' => 'blue', 'length_m' => '2', 'status' => CableStatus::Connected, 'notes' => null]);

    Subnet::factory()->create(['network' => '10.20.1.0/29']);
    foreach (range(1, 6) as $host) {
        IpAddress::factory()->create(['address' => '10.20.1.'.$host, 'mount_id' => $host === 1 ? $this->db->id : null]);
    }
});

it('measures the data centre for the dashboards', function (): void {
    $values = app(Metrics::class)->all();

    // 3 of 10 units; 600 W on a 550 W PDU; both devices on one supply; the /29 is full.
    expect($values['dcim.rack_space_used'])->toBe(30.0)
        ->and($values['dcim.power_over'])->toBe(1)
        ->and($values['dcim.power_high'])->toBe(0)
        ->and($values['dcim.single_fed'])->toBe(2)
        ->and($values['dcim.subnets_full'])->toBe(1);

    $this->actingAs($this->agent)->get(route('home'))->assertSee(__('dcim::reports.metrics.group'));
});

it('exports racks, equipment, cables, power and addresses to a workbook', function (): void {
    $this->actingAs($this->agent);
    Livewire::test(RackIndex::class)->call('export', 'xlsx');
    expect(Export::query()->where('type', DcimExport::TYPE)->exists())->toBeTrue();

    $export = Export::factory()->create(['type' => DcimExport::TYPE, 'user_id' => $this->agent->id]);
    [$racks, $equipment, $cables, $power, $addresses] = app(DcimExport::class)->sheets($export);
    $rows = fn ($sheet): array => array_map(fn (array $values): array => array_combine($sheet->headings, $values), iterator_to_array($sheet->rows, false));

    expect($rows($racks)[0])->toMatchArray(['Name' => 'R01', 'Units used' => 3, 'Power draw (W)' => 600])
        ->and(collect($rows($equipment))->firstWhere('Serial number', 'SN-DB01'))->toMatchArray(['Units' => 'U1–U2', 'Power draw (W)' => 500, 'Power supplies' => 'PDU A1', 'Two independent supplies' => false])
        ->and($rows($cables)[0])->toMatchArray(['Label' => 'C-001', 'End A port' => 'eth0', 'End B port' => 'Gi1', 'Length (m)' => 2.0])
        ->and($rows($power)[0])->toMatchArray(['Name' => 'PDU A1', 'Load (W)' => 600, 'Capacity (W)' => 550])
        ->and($rows($addresses)[0])->toMatchArray(['Address' => '10.20.1.1', 'Subnet' => '10.20.1.0/29']);

    $requester = DcimScenario::member($this->organization, DefaultRole::Requester);
    expect(fn () => app(RequestExport::class)->handle($requester, DcimExport::TYPE, ExportFormat::Xlsx, []))->toThrow(AuthorizationException::class);
});

it('prints a rack sheet with both sides, power and cables, and falls back without the PDF service', function (): void {
    $this->actingAs($this->agent);

    $this->get(route('dcim.racks.print', $this->rack))->assertOk()
        ->assertSee('Rack R01')->assertSee('db-01')->assertSee('SN-DB01')->assertSee('PDU A1 (3)')->assertSee('C-001')->assertSee(__('dcim::power.single_fed'));

    config(['core.pdf.gotenberg_url' => '']);
    $this->get(route('dcim.racks.pdf', $this->rack))->assertRedirect(route('dcim.racks.print', $this->rack));

    $this->actingAs(DcimScenario::member($this->organization, DefaultRole::Requester))->get(route('dcim.racks.print', $this->rack))->assertForbidden();

    $other = Organization::factory()->create();
    $foreign = app(OrganizationContext::class)->runAs($other, fn () => Rack::factory()->create(['location_id' => Location::factory()->create(['kind' => 'room'])->id]));
    $this->actingAs($this->agent)->get(route('dcim.racks.print', $foreign->id))->assertNotFound();
});
