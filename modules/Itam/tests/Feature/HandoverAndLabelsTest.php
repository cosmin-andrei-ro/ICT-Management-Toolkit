<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Application\Actions\AssignAsset;
use Modules\Itam\Application\Actions\ChangeAssetStatus;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\AssetAssignment;
use Modules\Itam\Http\Livewire\MyEquipment;
use Modules\Itam\Tests\Support\ItamScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->itam = ItamScenario::create();
    $this->laptop = $this->itam->laptop(['asset_tag' => 'AST-HO', 'manufacturer' => 'Dell', 'model' => 'Latitude', 'serial_number' => 'SN-HO']);
    app(AssignAsset::class)->handle($this->laptop, $this->itam->employee, $this->itam->manager, notes: 'With charger');
    $this->assignment = AssetAssignment::query()->sole();
});

it('lets the holder confirm receipt once, recorded with time and address', function (): void {
    $this->actingAs($this->itam->employee);

    Livewire::test(MyEquipment::class)
        ->assertSee('Please confirm you received this')
        ->call('confirmReceipt', $this->assignment->id)
        ->assertSee('Receipt confirmed');

    $first = $this->assignment->fresh()->acknowledged_at;
    $this->travel(1)->hour();
    Livewire::test(MyEquipment::class)->call('confirmReceipt', $this->assignment->id);

    expect($this->assignment->fresh()->acknowledged_at?->equalTo($first))->toBeTrue()
        ->and($this->assignment->fresh()->acknowledged_ip)->not->toBeNull()
        ->and(AuditLog::query()->where('auditable_id', $this->laptop->id)->whereJsonContains('new_values->handover_acknowledged_by', $this->itam->employee->name)->exists())->toBeTrue();
});

it('does not let anyone else confirm, nor confirm an asset already returned', function (): void {
    $this->actingAs($this->itam->colleague);
    Livewire::test(MyEquipment::class)->call('confirmReceipt', $this->assignment->id)->assertNotFound();

    app(ChangeAssetStatus::class)->handle($this->laptop, AssetStatus::InStock, $this->itam->manager);
    $this->actingAs($this->itam->employee);
    Livewire::test(MyEquipment::class)->call('confirmReceipt', $this->assignment->id)->assertNotFound();

    expect($this->assignment->fresh()->acknowledged_at)->toBeNull();
});

it('prints the protocol for the holder and IT staff, with the confirmation and the return', function (): void {
    $this->actingAs($this->itam->employee);
    Livewire::test(MyEquipment::class)->call('confirmReceipt', $this->assignment->id);

    $this->get(route('itam.handover', $this->assignment->id))
        ->assertOk()
        ->assertSee('Equipment handover protocol')
        ->assertSee('AST-HO')->assertSee('SN-HO')->assertSee('With charger')
        ->assertSee('Receipt confirmed electronically');

    app(ChangeAssetStatus::class)->handle($this->laptop, AssetStatus::InStock, $this->itam->agent, notes: 'Screen scratched');
    $this->actingAs($this->itam->agent)->get(route('itam.handover', $this->assignment->id))
        ->assertOk()->assertSee('Return')->assertSee('Screen scratched');

    $this->actingAs($this->itam->colleague)->get(route('itam.handover', $this->assignment->id))->assertForbidden();
});

it('keeps protocols inside their organization', function (): void {
    $other = Organization::factory()->create();
    $theirs = app(OrganizationContext::class)->runAs($other, function () {
        $asset = Asset::factory()->create();
        $assignment = new AssetAssignment;
        $assignment->forceFill(['organization_id' => $asset->organization_id, 'asset_id' => $asset->id, 'user_id' => $this->itam->employee->id, 'assigned_at' => now()])->save();

        return $assignment;
    });

    $this->actingAs($this->itam->employee)->get(route('itam.handover', $theirs->id))->assertNotFound();
});

it('prints QR labels for the assets the list shows, for the people who keep the register', function (): void {
    $this->itam->laptop(['asset_tag' => 'AST-L2']);
    $this->itam->laptop(['asset_tag' => 'AST-GONE', 'status' => 'disposed', 'disposed_on' => '2026-01-01', 'disposal_method' => 'recycled']);

    $this->actingAs($this->itam->manager)->get(route('itam.assets.labels'))
        ->assertOk()
        ->assertSee('AST-HO')->assertSee('AST-L2')->assertDontSee('AST-GONE')
        ->assertSee('data:image/svg+xml;base64,', false);

    $this->get(route('itam.assets.labels', ['search' => 'AST-L2']))->assertSee('AST-L2')->assertDontSee('AST-HO');
    $this->actingAs($this->itam->agent)->get(route('itam.assets.labels'))->assertForbidden();
});
