<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->memberOf($this->organization)->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->actingAs($this->user);
});

function auditEntriesFor(Department $department): array
{
    return AuditLog::query()
        ->where('auditable_type', 'core.department')
        ->where('auditable_id', $department->id)
        ->orderBy('created_at')->orderBy('id')
        ->get()->all();
}

it('records creation with the actor, organization and new values', function (): void {
    $department = Department::query()->create(['name' => 'Service Desk', 'code' => 'SD']);

    [$entry] = auditEntriesFor($department);

    expect($entry->event)->toBe(AuditEvent::Created)
        ->and($entry->user_id)->toBe($this->user->id)
        ->and($entry->organization_id)->toBe($this->organization->id)
        ->and($entry->old_values)->toBeNull()
        ->and($entry->new_values)->toMatchArray(['name' => 'Service Desk', 'code' => 'SD'])
        ->and($entry->new_values)->not->toHaveKeys(['created_at', 'updated_at']);
});

it('records only the changed fields with their old and new values', function (): void {
    $department = Department::query()->create(['name' => 'Service Desk', 'code' => 'SD']);

    $department->update(['code' => 'SVD']);

    $entry = auditEntriesFor($department)[1];

    expect($entry->event)->toBe(AuditEvent::Updated)
        ->and($entry->old_values)->toBe(['code' => 'SD'])
        ->and($entry->new_values)->toBe(['code' => 'SVD']);
});

it('does not record a save without changes', function (): void {
    $department = Department::query()->create(['name' => 'Service Desk']);

    $department->save();

    expect(auditEntriesFor($department))->toHaveCount(1);
});

it('records soft delete, restore and force delete as distinct events', function (): void {
    $department = Department::query()->create(['name' => 'Service Desk']);

    $department->delete();
    $department->restore();
    $department->forceDelete();

    expect(array_map(fn (AuditLog $entry): AuditEvent => $entry->event, auditEntriesFor($department)))->toBe([
        AuditEvent::Created,
        AuditEvent::Deleted,
        AuditEvent::Restored,
        AuditEvent::ForceDeleted,
    ]);
});

it('never records hidden attributes such as passwords', function (): void {
    $user = User::factory()->create();
    $user->update(['password' => 'a-new-long-password']);

    $entries = AuditLog::query()->where('auditable_id', $user->id)->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()?->new_values)->not->toHaveKeys(['password', 'remember_token']);
});

it('captures client metadata for HTTP requests', function (): void {
    Route::middleware('web')->post('/__test/departments', function () {
        Department::query()->create(['name' => 'From HTTP']);

        return response()->noContent();
    });

    $this->withHeaders(['User-Agent' => 'AuditTest/1.0', 'X-Request-Id' => 'req-123'])
        ->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->post('/__test/departments')
        ->assertNoContent()
        ->assertHeader('X-Request-Id', 'req-123');

    $entry = AuditLog::query()->where('new_values->name', 'From HTTP')->sole();

    expect($entry->ip_address)->toBe('127.0.0.1')
        ->and($entry->user_agent)->toBe('AuditTest/1.0')
        ->and($entry->request_id)->toBe('req-123');
});

it('replaces a malformed client request id', function (): void {
    $response = $this->withHeaders(['X-Request-Id' => 'bad id; drop'])->get('/up');

    expect($response->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

it('leaves client metadata empty outside HTTP requests', function (): void {
    $department = Department::query()->create(['name' => 'From console']);

    [$entry] = auditEntriesFor($department);

    expect($entry->ip_address)->toBeNull()
        ->and($entry->request_id)->toBeNull();
});

it('rejects updates to audit entries at the database level', function (): void {
    Department::query()->create(['name' => 'Service Desk']);

    DB::table('core_audit_logs')->update(['event' => 'deleted']);
})->throws(QueryException::class, 'append-only');

it('rejects deletion of audit entries at the database level', function (): void {
    Department::query()->create(['name' => 'Service Desk']);

    AuditLog::query()->delete();
})->throws(QueryException::class, 'append-only');

it('rejects truncation of the audit log at the database level', function (): void {
    DB::statement('TRUNCATE core_audit_logs');
})->throws(QueryException::class, 'append-only');

it('rejects unknown audit events at the database level', function (): void {
    DB::table('core_audit_logs')->insert([
        'id' => (string) str()->uuid7(),
        'event' => 'tampered',
        'auditable_type' => 'core.department',
        'auditable_id' => (string) str()->uuid7(),
    ]);
})->throws(QueryException::class, 'core_audit_logs_event_check');
