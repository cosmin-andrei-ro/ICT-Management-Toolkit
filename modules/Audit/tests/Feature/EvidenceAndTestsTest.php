<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Applications\Domain\Models\Application;
use Modules\Audit\Application\Notifications\ControlTestDue;
use Modules\Audit\Application\Reminders\SendTestReminders;
use Modules\Audit\Domain\Enums\EvidenceKind;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Evidence;
use Modules\Audit\Http\Livewire\ControlForm;
use Modules\Audit\Http\Livewire\ControlIndex;
use Modules\Audit\Http\Livewire\ShowControl;
use Modules\Audit\Tests\Support\ComplianceScenario;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Domain\Models\Risk;
use Modules\Ticketing\Domain\Models\Ticket;

beforeEach(function (): void {
    Storage::fake('attachments');
    Notification::fake();
    $this->compliance = ComplianceScenario::create();
    $this->control = Control::factory()->create(['name' => 'Backups restored and tested', 'owner_id' => $this->compliance->owner->id]);
});

it('finds records of every module that can serve as evidence, as the viewer may see them', function (): void {
    Risk::factory()->create(['title' => 'Backup ransomware']);
    Application::factory()->create(['name' => 'Backup console', 'code' => 'APP-BKP']);
    $plan = Plan::factory()->create(['name' => 'Backup DRP']);
    Exercise::factory()->create(['plan_id' => $plan->id, 'title' => 'Backup restore test']);
    BusinessProcess::factory()->create(['name' => 'Backup operations']);
    Contract::factory()->create(['title' => 'Backup storage contract']);
    Ticket::factory()->create(['title' => 'Backup schedule change']);

    $records = app(LinkedRecords::class);
    expect(array_keys($records->searchable()))->toEqualCanonicalizing(['risk.risk', 'applications.application', 'bcm.plan', 'bcm.exercise', 'bia.process', 'core.contract', 'ticketing.ticket']);

    $agent = $this->compliance->member(DefaultRole::Agent);
    foreach (array_keys($records->searchable()) as $type) {
        $viewer = $type === 'ticketing.ticket' ? $agent : $this->compliance->manager;
        expect($records->search($type, 'backup', $viewer))->toHaveCount(1, $type);
    }

    expect($records->search('risk.risk', 'R-0001', $this->compliance->manager))->toHaveCount(1)
        // Searching every ticket is for those who see every ticket; employees see none of the rest.
        ->and($records->search('ticketing.ticket', 'backup', $this->compliance->manager))->toBe([])
        ->and($records->search('risk.risk', 'backup', $this->compliance->employee))->toBe([]);
});

it('lets the owner link records and upload documents as evidence, read by auditors', function (): void {
    $risk = Risk::factory()->create(['title' => 'Backups cannot be restored']);
    $this->actingAs($this->compliance->manager);
    Livewire::test(ShowControl::class, ['control' => $this->control])
        ->set('evidenceType', 'risk.risk')->set('evidenceSearch', 'restored')->assertSee('Backups cannot be restored')
        ->set('evidenceNote', 'Treated by this control')
        ->call('linkEvidence', $risk->id)->assertHasNoErrors()
        ->call('linkEvidence', $risk->id)->assertHasErrors('evidenceSearch');

    // A record of another organization cannot be linked.
    app(OrganizationContext::class)->set(Organization::factory()->create());
    $foreign = Risk::factory()->create();
    app(OrganizationContext::class)->set($this->compliance->organization);
    Livewire::test(ShowControl::class, ['control' => $this->control])->set('evidenceType', 'risk.risk')->call('linkEvidence', $foreign->id)->assertHasErrors('evidenceSearch');

    $this->actingAs($this->compliance->owner);
    Livewire::test(ShowControl::class, ['control' => $this->control])
        ->set('document', UploadedFile::fake()->createWithContent('restore.exe', "MZ\x90\x00binary"))->call('uploadEvidence')->assertHasErrors('document')
        ->set('document', UploadedFile::fake()->createWithContent('restore-report.pdf', "%PDF-1.4\n%test\n"))
        ->set('documentValidUntil', today()->subDay()->toDateString())
        ->call('uploadEvidence')->assertHasNoErrors()
        ->assertSee('restore-report.pdf')->assertSee('expired on');

    $link = Evidence::query()->where('kind', EvidenceKind::Link)->sole();
    $file = Evidence::query()->where('kind', EvidenceKind::File)->sole();
    expect($link->title)->toBe('R-0001 Backups cannot be restored')->and($link->description)->toBe('Treated by this control')
        ->and($file->title)->toBe('restore-report.pdf')
        ->and(AuditLog::query()->where('auditable_id', $file->id)->exists())->toBeTrue();
    $attachment = $file->attachments()->sole();

    // Auditors read and download, change nothing; the owner, a plain employee, sees the risk only by its title.
    Livewire::test(ShowControl::class, ['control' => $this->control])->assertSee('R-0001 Backups cannot be restored')->assertSee('you cannot open this record');
    $this->actingAs($this->compliance->auditor);
    Livewire::test(ShowControl::class, ['control' => $this->control])
        ->assertSeeHtml(route('risk.risks.show', $risk))
        ->call('removeEvidence', $link->id)->assertForbidden();
    $this->get(route('core.attachments.download', $attachment))->assertOk();
    $this->actingAs($this->compliance->employee);
    $this->get(route('core.attachments.download', $attachment))->assertForbidden();

    $this->actingAs($this->compliance->owner);
    Livewire::test(ShowControl::class, ['control' => $this->control])->call('removeEvidence', $link->id)->assertDontSee('R-0001');
    expect(Evidence::query()->find($link->id))->toBeNull()->and($link->fresh()?->deleted_at)->not->toBeNull();
});

it('schedules periodic tests, records them with their document and reminds owners once', function (): void {
    $this->actingAs($this->compliance->manager);
    Livewire::test(ControlForm::class, ['control' => $this->control])->set('testInterval', '6')->call('save')->assertHasNoErrors();
    expect($this->control->fresh()?->next_test_on?->toDateString())->toBe(CarbonImmutable::today()->addMonthsNoOverflow(6)->toDateString());

    // Due within the notice period: the owner is told once per due date.
    $this->control->forceFill(['next_test_on' => today()->addDays(10)])->save();
    expect(app(SendTestReminders::class)->handle())->toBe(1)
        ->and(app(SendTestReminders::class)->handle())->toBe(0);
    Notification::assertSentTo($this->compliance->owner, ControlTestDue::class);

    $this->actingAs($this->compliance->auditor);
    Livewire::test(ShowControl::class, ['control' => $this->control])->set('testMethod', 'x')->call('recordTest')->assertForbidden();

    $this->actingAs($this->compliance->owner);
    $page = Livewire::test(ShowControl::class, ['control' => $this->control])
        ->set('testedOn', today()->addDay()->toDateString())->set('testMethod', 'Restore of a sample')->call('recordTest')->assertHasErrors('testedOn')
        ->set('testedOn', today()->subDays(2)->toDateString())->set('testMethod', '')->call('recordTest')->assertHasErrors('testMethod');
    $page->set('testMethod', 'Restored the payroll database from last night\'s backup to a test server')
        ->set('testResult', 'partially_effective')
        ->set('testNotes', 'Restore took 5 hours, above the 4-hour RTO')
        ->set('testDocument', UploadedFile::fake()->createWithContent('restore-log.pdf', "%PDF-1.4\n%log\n"))
        ->call('recordTest')->assertHasNoErrors()
        ->assertSee('Partly effective')->assertSee('restore-log.pdf');

    $control = $this->control->fresh();
    expect($control?->last_tested_on?->toDateString())->toBe(today()->subDays(2)->toDateString())
        ->and($control?->next_test_on?->toDateString())->toBe(CarbonImmutable::today()->subDays(2)->addMonthsNoOverflow(6)->toDateString())
        ->and(Evidence::query()->whereNotNull('test_id')->sole()->title)->toBe('Test of '.today()->subDays(2)->toDateString());

    // Overdue tests stand out in the register.
    $control?->forceFill(['next_test_on' => today()->subDay()])->save();
    $this->actingAs($this->compliance->auditor);
    Livewire::test(ControlIndex::class)->set('testOverdue', true)->assertSee('Backups restored and tested');
    Control::factory()->create(['name' => 'Never tested control']);
    Livewire::test(ControlIndex::class)->set('testOverdue', true)->assertDontSee('Never tested control');
});
