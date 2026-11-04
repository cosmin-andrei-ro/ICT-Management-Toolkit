<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Audit\Application\Actions\AddFramework;
use Modules\Audit\Application\Exports\ComplianceExport;
use Modules\Audit\Application\Notifications\ComplianceDigest;
use Modules\Audit\Application\Reminders\SendComplianceDigest;
use Modules\Audit\Domain\Models\Audit;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Audit\Http\Livewire\ComplianceIndex;
use Modules\Audit\Tests\Support\ComplianceScenario;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Risks\RiskSignal;
use Modules\Core\Domain\Risks\RiskSignals;

beforeEach(function (): void {
    Notification::fake();
    $this->compliance = ComplianceScenario::create();
    app(AddFramework::class)->fromCatalog('iso27001');
    $this->iso = Framework::query()->sole();
    $this->control = Control::factory()->create(['name' => 'Multifactor authentication on remote access', 'status' => 'implemented', 'owner_id' => $this->compliance->owner->id]);
    $this->authentication = Requirement::query()->where('reference', '8.5')->sole();
    $this->authentication->forceFill(['is_applicable' => true, 'justification' => 'Remote access to every system'])->save();
    $this->control->requirements()->attach($this->authentication->id, ['organization_id' => $this->control->organization_id]);
    Requirement::query()->where('reference', '7.4')->sole()->forceFill(['is_applicable' => false, 'justification' => 'No premises of our own'])->save();
    Requirement::query()->where('reference', '5.18')->sole()->forceFill(['is_applicable' => true])->save();
    $this->audit = Audit::factory()->create(['title' => 'Internal audit 2026']);
    $this->major = Finding::factory()->create(['audit_id' => $this->audit->id, 'title' => 'Access rights never reviewed', 'severity' => 'major_nonconformity', 'owner_id' => $this->compliance->owner->id, 'due_on' => today()->subDay()]);
});

/**
 * @return list<string>
 */
function complianceSignalKeys(): array
{
    return array_values(array_filter(
        array_map(fn (RiskSignal $signal): string => $signal->key, app(RiskSignals::class)->collect()['signals']),
        fn (string $key): bool => str_starts_with($key, 'audit.'),
    ));
}

it('prints the Statement of Applicability and the compliance report, also as PDF', function (): void {
    $this->actingAs($this->compliance->auditor);
    $this->get(route('audit.frameworks.soa', $this->iso))->assertOk()
        ->assertSee('Statement of Applicability - ISO/IEC 27001:2022 Annex A')
        ->assertSeeInOrder(['8.5', 'Secure authentication', 'Yes', 'Remote access to every system', 'C-001 Multifactor authentication on remote access', 'Met'])
        ->assertSee('No premises of our own')
        ->assertSee('93 requirements: 92 applicable (or not decided), 1 excluded, 90 not decided yet.');

    $this->get(route('audit.report'))->assertOk()
        ->assertSee('Compliance report')->assertSee('ISO/IEC 27001:2022 Annex A')
        ->assertSee('AUD-001 · Internal audit 2026')->assertSee('F-001 · Access rights never reviewed')
        ->assertSee('Implemented: 1')->assertDontSee('Download PDF');

    config(['core.pdf.gotenberg_url' => 'http://gotenberg:3000']);
    Http::fake(['gotenberg:3000/*' => Http::response('%PDF-1.7 fake', 200)]);
    $this->get(route('audit.frameworks.soa.pdf', $this->iso))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->body(), 'Remote access to every system'));
    $this->get(route('audit.report.pdf'))->assertOk();

    $this->actingAs($this->compliance->employee);
    $this->get(route('audit.report'))->assertForbidden();
    $this->get(route('audit.frameworks.soa', $this->iso))->assertForbidden();
    $this->get(route('audit.report.pdf'))->assertForbidden();
});

it('exports applicability, controls, tests, evidence, findings and actions to XLSX', function (): void {
    $this->actingAs($this->compliance->auditor);
    Livewire::test(ComplianceIndex::class)->call('export', 'xlsx');
    expect(Export::query()->where('type', ComplianceExport::TYPE)->exists())->toBeTrue();

    $export = Export::factory()->create(['type' => ComplianceExport::TYPE, 'user_id' => $this->compliance->auditor->id]);
    [$applicability, $controls, , , $findings] = app(ComplianceExport::class)->sheets($export);
    $row = collect(iterator_to_array($applicability->rows, false))->map(fn (array $values): array => array_combine($applicability->headings, $values))->firstWhere('Reference', '8.5');
    expect($row)->toMatchArray(['Framework' => 'ISO/IEC 27001:2022 Annex A', 'Applicable' => true, 'Controls' => 'C-001 Multifactor authentication on remote access', 'State' => 'Met'])
        ->and(iterator_to_array($controls->rows, false)[0])->toMatchArray([0 => 'C-001', 3 => 'Implemented', 5 => 1])
        ->and(iterator_to_array($findings->rows, false)[0])->toMatchArray([0 => 'F-001', 1 => 'AUD-001', 3 => 'Major nonconformity']);

    expect(fn () => app(RequestExport::class)->handle($this->compliance->employee, ComplianceExport::TYPE, ExportFormat::Xlsx, []))->toThrow(AuthorizationException::class);
});

it('emails compliance managers what needs attention each week', function (): void {
    expect(app(SendComplianceDigest::class)->figures(today()->toImmutable()))
        ->toMatchArray(['major_open' => 1, 'findings_overdue' => 1, 'requirements_not_covered' => 1, 'findings_to_verify' => 0]);
    expect(app(SendComplianceDigest::class)->handle())->toBe(1);
    Notification::assertSentTo($this->compliance->manager, ComplianceDigest::class, fn (ComplianceDigest $digest): bool => $digest->figures['major_open'] === 1 && ! isset($digest->figures['findings_to_verify']));
    Notification::assertNotSentTo($this->compliance->auditor, ComplianceDigest::class);
});

it('signals open major nonconformities and uncovered requirements to the risk register', function (): void {
    expect(complianceSignalKeys())->toEqualCanonicalizing(['audit.major:'.$this->major->id, 'audit.uncovered:'.$this->iso->id]);

    $this->major->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
    $this->control->requirements()->attach(Requirement::query()->where('reference', '5.18')->value('id'), ['organization_id' => $this->control->organization_id]);
    expect(complianceSignalKeys())->toBe([]);
});
