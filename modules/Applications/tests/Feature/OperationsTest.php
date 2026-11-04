<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Applications\Application\Notifications\CertificateExpiring;
use Modules\Applications\Application\Reminders\SendCertificateReminders;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Http\Livewire\SheetPage;
use Modules\Applications\Http\Livewire\ShowApplication;
use Modules\Applications\Tests\Support\ApplicationsScenario;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Cmdb\Application\Actions\ManageRelationships;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\CiLink;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;

beforeEach(function (): void {
    Notification::fake();
    $this->apps = ApplicationsScenario::create();
    $this->actingAs($this->apps->technicalOwner);
    Livewire::test(ShowApplication::class, ['application' => $this->apps->ticketing])->call('startSheet');
    $this->sheet = Sheet::query()->sole();
});

it('reminds the owners of certificates of the documentation in force, once per expiry date', function (): void {
    $certificates = fn (string $expiry): array => ['8_1' => ['certificates' => [
        ['name' => 'ticketing.company.local', 'expires_on' => $expiry],
        ['name' => 'sdapp01.company.local', 'expires_on' => today()->addDays(100)->toDateString()],
        ['name' => 'SD-Graph-Client', 'expires_on' => null],
    ]]];
    $this->sheet->forceFill(['status' => 'approved', 'major' => 1, 'minor' => 0, 'approved_at' => now(), 'content' => $certificates(today()->addDays(10)->toDateString())])->save();

    expect(app(SendCertificateReminders::class)->handle())->toBe(1);
    Notification::assertSentTo($this->apps->technicalOwner, CertificateExpiring::class, fn (CertificateExpiring $mail): bool => $mail->certificate === 'ticketing.company.local');
    Notification::assertNotSentTo($this->apps->businessOwner, CertificateExpiring::class);
    expect(app(SendCertificateReminders::class)->handle())->toBe(0);

    // Renewed, and expiring again within the notice: announced again.
    $this->sheet->forceFill(['content' => $certificates(today()->addDays(20)->toDateString())])->save();
    expect(app(SendCertificateReminders::class)->handle())->toBe(1);
});

it('shows what the BIA, the CMDB and the recovery plans know in the continuity chapter', function (): void {
    $payroll = BusinessProcess::factory()->create(['name' => 'Incident handling', 'rto_minutes' => 240, 'rpo_minutes' => 60, 'tier' => 2, 'approved_at' => now()]);
    $type = CiType::factory()->create(['key' => 'server']);
    $app = ConfigurationItem::factory()->create(['type_id' => $type->id, 'name' => 'SDAPP01']);
    $db = ConfigurationItem::factory()->create(['type_id' => $type->id, 'name' => 'SDDB01', 'recovery_time_minutes' => 120]);
    app(ManageRelationships::class)->add($app, RelationshipType::DependsOn, $db);
    $plan = Plan::factory()->create(['name' => 'Ticketing DRP', 'owner_id' => $this->apps->technicalOwner->id]);
    $version = PlanVersion::factory()->create(['plan_id' => $plan->id, 'status' => 'approved', 'decided_at' => now()]);
    (new CiLink)->forceFill(['organization_id' => $app->organization_id, 'configuration_item_id' => $app->id, 'linkable_type' => 'bcm.plan_version', 'linkable_id' => $version->id])->save();

    Livewire::test(SheetPage::class, ['sheet' => $this->sheet])->call('openChapter', 5)->call('linkItem', $app->id)
        ->call('openChapter', 10)
        ->set('processSearch', 'Incident')->call('addProcess', '10_1', 'processes', $payroll->id)
        ->call('addProcess', '10_1', 'processes', '01999999-0000-7000-8000-000000000000')
        ->call('save')->assertHasNoErrors()
        ->assertSee('Incident handling: RTO 4 hours, RPO 1 hour, tier 2')
        ->assertSee('The application must be back within 4 hours and lose at most 1 hour of data.')
        ->assertSee('SDDB01')->assertSee('can be back within 2 hours')
        ->assertSee('Ticketing DRP');

    expect($this->sheet->fresh()->section('10_1')['processes'])->toBe([$payroll->id]);
});

it('fills the decommissioning checklist with the template\'s steps and shows the standard glossary', function (): void {
    Livewire::test(SheetPage::class, ['sheet' => $this->sheet])->call('openChapter', 15)
        ->call('addTemplateSteps', '15_2', 'checklist')
        ->set('form.15_1.reason', 'replaced')
        ->call('save')->assertHasNoErrors()
        ->call('openChapter', 16)
        ->assertSee('Maximum Tolerable Period of Disruption')
        ->call('addRow', '16', 'terms')->set('form.16.terms.0.term', 'TKT')->set('form.16.terms.0.meaning', 'Ticketing Platform')
        ->call('save')->assertHasNoErrors();

    $checklist = $this->sheet->fresh()->section('15_2')['checklist'];
    expect($checklist)->toHaveCount(12)
        ->and($checklist[3]['activity'])->toBe('Disable or delete technical users and service accounts (see 7)')
        ->and($checklist[3]['status'])->toBe('not_started')
        ->and($this->sheet->fresh()->section('16')['terms'][0]['term'])->toBe('TKT');
});
