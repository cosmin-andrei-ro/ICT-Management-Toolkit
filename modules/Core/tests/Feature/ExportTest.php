<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Application\Exports\PruneExports;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Tests\Support\FakeExportSource;

beforeEach(function (): void {
    Storage::fake('exports');
    app(SyncPermissions::class)->handle();
    app(ExportRegistry::class)->register('test.export', FakeExportSource::class, 'core.admin.access');

    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->user = User::factory()->memberOf($this->organization)->create();
    $this->user->assignRole(DefaultRole::Administrator->value);
    $this->other = User::factory()->memberOf($this->organization)->create();
    $this->other->assignRole(DefaultRole::Administrator->value);
});

function requestExport(User $user, ExportFormat $format = ExportFormat::Csv): Export
{
    return app(RequestExport::class)->handle($user, 'test.export', $format, ['from' => '2026-10-01'])->fresh();
}

it('generates a CSV for the requester, with formulas neutralized, and audits the request', function (): void {
    $export = requestExport($this->user);

    expect($export->status)->toBe(ExportStatus::Ready)
        ->and($export->row_count)->toBe(3)
        ->and($export->filename)->toBe('test-export.csv')
        ->and($export->expires_at?->isFuture())->toBeTrue()
        ->and(AuditLog::query()->where('event', AuditEvent::Exported)->where('auditable_id', $export->id)->exists())->toBeTrue();

    $csv = Storage::disk('exports')->get((string) $export->path);
    expect($csv)->toContain('"\'=HYPERLINK(')
        ->toContain("'+cmd")
        ->toContain('Plain,-5')
        ->not->toContain('About');
});

it('writes every sheet to XLSX', function (): void {
    $export = requestExport($this->user, ExportFormat::Xlsx);
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, Storage::disk('exports')->get((string) $export->path));

    $zip = new ZipArchive;
    $zip->open($path);
    $workbook = (string) $zip->getFromName('xl/workbook.xml');
    $zip->close();
    unlink($path);

    expect($export->row_count)->toBe(4)
        ->and($workbook)->toContain('name="Rows"')->toContain('name="About period"');
});

it('refuses requests without the permission of the export type', function (): void {
    $plain = User::factory()->memberOf($this->organization)->create();

    requestExport($plain);
})->throws(AuthorizationException::class);

it('lets only the requester download, while it lasts, and audits downloads', function (): void {
    $export = requestExport($this->user);

    $this->actingAs($this->user)->get(route('core.exports.download', $export))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=utf-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertDownload('test-export.csv');
    expect(AuditLog::query()->where('event', AuditEvent::ExportDownloaded)->count())->toBe(1);

    $this->actingAs($this->other)->get(route('core.exports.download', $export))->assertForbidden();

    $export->forceFill(['expires_at' => now()->subMinute()])->save();
    $this->actingAs($this->user)->get(route('core.exports.download', $export))->assertForbidden();
});

it('stops downloads once the requester loses the permission', function (): void {
    $export = requestExport($this->user);
    $this->user->syncRoles([DefaultRole::Requester->value]);

    $this->actingAs($this->user->fresh())->get(route('core.exports.download', $export))->assertForbidden();
});

it('keeps exports of other organizations out of reach', function (): void {
    $foreign = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Export::factory()->ready()->create(['user_id' => $this->user->id, 'type' => 'test.export']));

    $this->actingAs($this->user)->get(route('core.exports.download', $foreign))->assertNotFound();
});

it('deletes expired exports and their files, and stale requests', function (): void {
    $expired = requestExport($this->user);
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();
    $current = requestExport($this->user);
    $stuck = Export::factory()->create(['user_id' => $this->user->id, 'created_at' => now()->subDays(2)]);

    expect(app(PruneExports::class)->handle())->toBe(2)
        ->and(Export::query()->pluck('id')->all())->toBe([$current->id])
        ->and(Storage::disk('exports')->exists((string) $expired->path))->toBeFalse()
        ->and(Storage::disk('exports')->exists((string) $current->path))->toBeTrue();
});

it('marks an export failed when its source breaks', function (): void {
    app(ExportRegistry::class)->register('test.broken', stdClass::class, 'core.admin.access');

    $export = app(RequestExport::class)->handle($this->user, 'test.broken', ExportFormat::Csv, []);

    expect($export->fresh()->status)->toBe(ExportStatus::Failed);
});
