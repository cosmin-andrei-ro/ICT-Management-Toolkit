<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Application\Retention\ApplyRetention;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\RetentionSettings;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Pages\DataRetentionPage;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    $this->other = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
});

function auditEntry(?Organization $organization, int $yearsAgo, string $marker): void
{
    (new AuditLog)->forceFill([
        'organization_id' => $organization?->id, 'event' => AuditEvent::Login, 'auditable_type' => 'core.user', 'auditable_id' => (string) Str::uuid(),
        'new_values' => ['marker' => $marker], 'created_at' => now()->subYears($yearsAgo)->subDay(),
    ])->save();
}

function markers(): array
{
    return AuditLog::query()->get()->map(fn (AuditLog $entry): mixed => $entry->new_values['marker'] ?? null)->filter()->values()->all();
}

it('deletes audit entries past each organization\'s period, and those of no organization after the longest', function (): void {
    app(OrganizationContext::class)->runAs($this->other, fn () => (new RetentionSettings)->forceFill(['periods' => ['core.audit_log' => 8]])->save());
    auditEntry($this->organization, 6, 'ours-old');
    auditEntry($this->organization, 2, 'ours-recent');
    auditEntry($this->other, 6, 'theirs-six');
    auditEntry(null, 6, 'nobody-six');
    auditEntry(null, 9, 'nobody-nine');

    app(ApplyRetention::class)->handle();

    expect(markers())->toContain('ours-recent', 'theirs-six', 'nobody-six')->not->toContain('ours-old', 'nobody-nine')
        ->and(AuditLog::query()->where('event', AuditEvent::RetentionApplied)->where('organization_id', $this->organization->id)->sole()->new_values)->toBe(['core.audit_log' => 1]);
});

it('keeps everything of no organization while one organization keeps its log forever', function (): void {
    app(OrganizationContext::class)->runAs($this->other, fn () => (new RetentionSettings)->forceFill(['periods' => ['core.audit_log' => null]])->save());
    auditEntry(null, 30, 'nobody-thirty');
    auditEntry($this->other, 30, 'theirs-thirty');

    app(ApplyRetention::class)->handle();

    expect(markers())->toContain('nobody-thirty', 'theirs-thirty');
});

it('keeps the audit log closed to deletion outside the retention function', function (): void {
    auditEntry($this->organization, 20, 'ancient');

    expect(fn () => DB::table('core_audit_logs')->delete())->toThrow(QueryException::class);
});

it('lets administrators choose the periods, empty keeping forever', function (): void {
    Filament::setCurrentPanel('admin');
    $admin = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
    $admin->assignRole(DefaultRole::Administrator->value);
    $lead = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
    $lead->assignRole(DefaultRole::TeamLead->value);
    $lead->givePermissionTo('core.admin.access');

    $this->actingAs($lead)->get('/admin/data-retention')->assertForbidden();
    $this->actingAs($admin)->get('/admin/data-retention')->assertOk()->assertSee('Audit log')->assertSee('Closed tickets');

    Livewire::test(DataRetentionPage::class)
        ->assertSet('data.core__audit_log', 5)->assertSet('data.ticketing__closed_tickets', 3)
        ->set('data.core__audit_log', 0)->call('save')->assertHasErrors()
        ->set('data.core__audit_log', 7)->set('data.ticketing__closed_tickets', null)->call('save')->assertHasNoErrors();

    $settings = RetentionSettings::current();
    expect($settings->years('core.audit_log', 5))->toBe(7)->and($settings->years('ticketing.closed_tickets', 3))->toBeNull();
});
