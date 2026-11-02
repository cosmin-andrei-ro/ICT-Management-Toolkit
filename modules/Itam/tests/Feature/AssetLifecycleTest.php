<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Application\Actions\AssignAsset;
use Modules\Itam\Application\Actions\ChangeAssetStatus;
use Modules\Itam\Application\Actions\DisposeAsset;
use Modules\Itam\Application\Actions\SaveAsset;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Enums\DisposalMethod;
use Modules\Itam\Domain\Exceptions\AssetTransitionNotAllowed;
use Modules\Itam\Domain\Models\AssetAssignment;
use Modules\Itam\Tests\Support\ItamScenario;

beforeEach(function (): void {
    $this->itam = ItamScenario::create();
    $this->actingAs($this->itam->manager);
});

it('gives new assets the next free tag, skipping tags taken by hand', function (): void {
    $save = app(SaveAsset::class);
    $this->itam->laptop(['asset_tag' => 'ast-000002']);

    $first = $save->handle(null, $this->itam->laptops, ['manufacturer' => 'Dell'], ['memory_gb' => 16]);
    $second = $save->handle(null, $this->itam->laptops, ['status' => 'ordered'], []);
    $own = $save->handle(null, $this->itam->laptops, ['asset_tag' => 'OLD-17'], []);

    expect($first->asset_tag)->toBe('AST-000001')
        ->and($first->properties)->toBe(['memory_gb' => 16])
        ->and($first->status)->toBe(AssetStatus::InStock)
        ->and($second->asset_tag)->toBe('AST-000003')
        ->and($second->status)->toBe(AssetStatus::Ordered)
        ->and($own->asset_tag)->toBe('OLD-17');
});

it('hands an asset out, moves it to someone else and takes it back, keeping the history', function (): void {
    $laptop = $this->itam->laptop();
    $assign = app(AssignAsset::class);

    $assign->handle($laptop, $this->itam->employee, $this->itam->manager, notes: 'New starter');
    $assign->handle($laptop, $this->itam->colleague, $this->itam->agent);
    app(ChangeAssetStatus::class)->handle($laptop, AssetStatus::InStock, $this->itam->agent, notes: 'Left the company');

    $history = AssetAssignment::query()->where('asset_id', $laptop->id)->orderBy('assigned_at')->orderBy('id')->get();
    expect($laptop->fresh()->status)->toBe(AssetStatus::InStock)
        ->and($laptop->fresh()->assigned_to_id)->toBeNull()
        ->and($history->pluck('user_id')->all())->toBe([$this->itam->employee->id, $this->itam->colleague->id])
        ->and($history->every(fn (AssetAssignment $assignment): bool => $assignment->returned_at !== null))->toBeTrue()
        ->and($history[0]->notes)->toBe('New starter')
        ->and($history[1]->return_notes)->toBe('Left the company')
        ->and(AuditLog::query()->where('auditable_id', $laptop->id)->where('event', 'updated')->count())->toBeGreaterThanOrEqual(3);
});

it('keeps the holder while the asset is away for repair', function (): void {
    $laptop = $this->itam->laptop();
    app(AssignAsset::class)->handle($laptop, $this->itam->employee, $this->itam->manager);
    $change = app(ChangeAssetStatus::class);

    $change->handle($laptop, AssetStatus::InRepair, $this->itam->agent);
    expect($laptop->fresh()->assigned_to_id)->toBe($this->itam->employee->id);

    expect(fn () => app(AssignAsset::class)->handle($laptop, $this->itam->colleague, $this->itam->agent))->toThrow(AssetTransitionNotAllowed::class);

    $change->handle($laptop, AssetStatus::Deployed, $this->itam->agent);
    expect($laptop->fresh()->status)->toBe(AssetStatus::Deployed)
        ->and(AssetAssignment::query()->where('asset_id', $laptop->id)->count())->toBe(1);
});

it('refuses moves the life cycle does not allow', function (): void {
    $laptop = $this->itam->laptop();
    app(AssignAsset::class)->handle($laptop, $this->itam->employee, $this->itam->manager);
    $change = app(ChangeAssetStatus::class);

    expect(fn () => $change->handle($laptop, AssetStatus::Retired, $this->itam->manager))->toThrow(AssetTransitionNotAllowed::class)
        ->and(fn () => app(DisposeAsset::class)->handle($laptop, CarbonImmutable::today(), DisposalMethod::Recycled, true))->toThrow(AssetTransitionNotAllowed::class)
        ->and(fn () => $change->handle($laptop, AssetStatus::Disposed, $this->itam->manager))->toThrow(AssetTransitionNotAllowed::class)
        ->and(fn () => app(AssignAsset::class)->handle($laptop, $this->itam->employee, $this->itam->manager))->toThrow(AssetTransitionNotAllowed::class);
});

it('closes the assignment of a lost asset, and disposes of it for good', function (): void {
    $laptop = $this->itam->laptop();
    app(AssignAsset::class)->handle($laptop, $this->itam->employee, $this->itam->manager);

    app(ChangeAssetStatus::class)->handle($laptop, AssetStatus::Lost, $this->itam->manager, notes: 'Stolen from a car');
    app(DisposeAsset::class)->handle($laptop, CarbonImmutable::parse('2026-11-01'), DisposalMethod::Destroyed, null, 'Written off');

    $laptop->refresh();
    expect($laptop->status)->toBe(AssetStatus::Disposed)
        ->and($laptop->assigned_to_id)->toBeNull()
        ->and($laptop->disposal_method)->toBe(DisposalMethod::Destroyed)
        ->and($laptop->data_wiped)->toBeNull()
        ->and($laptop->status->next())->toBe([])
        ->and(AssetAssignment::query()->sole()->return_notes)->toBe('Stolen from a car');
});

it('hands assets only to active members of the organization, at its own locations', function (): void {
    $laptop = $this->itam->laptop();
    $other = Organization::factory()->create();
    $stranger = User::factory()->memberOf($other)->create();
    $inactive = User::factory()->memberOf($this->itam->organization)->create(['is_active' => false]);
    $theirRoom = app(OrganizationContext::class)->runAs($other, fn () => Location::factory()->create());

    expect(fn () => app(AssignAsset::class)->handle($laptop, $stranger, $this->itam->manager))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(AssignAsset::class)->handle($laptop, $inactive, $this->itam->manager))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(AssignAsset::class)->handle($laptop, $this->itam->employee, $this->itam->manager, $theirRoom->id))->toThrow(InvalidArgumentException::class)
        ->and($laptop->fresh()->status)->toBe(AssetStatus::InStock);
});

it('enforces one holder per asset in the database too', function (): void {
    $laptop = $this->itam->laptop();
    app(AssignAsset::class)->handle($laptop, $this->itam->employee, $this->itam->manager);

    $second = new AssetAssignment;
    $second->forceFill(['organization_id' => $laptop->organization_id, 'asset_id' => $laptop->id, 'user_id' => $this->itam->colleague->id, 'assigned_at' => now()]);

    expect(fn () => $second->save())->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});
