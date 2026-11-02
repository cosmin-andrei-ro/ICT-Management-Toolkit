<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Applications\Http\Livewire\ShowApplication;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Models\Export;
use Modules\Risk\Application\Actions\ManageRiskActions;
use Modules\Risk\Application\Exports\RiskRegisterExport;
use Modules\Risk\Application\Notifications\RiskDigest;
use Modules\Risk\Application\Reminders\SendRiskDigest;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Http\Livewire\RiskIndex;
use Modules\Risk\Tests\Support\RiskScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->risks = RiskScenario::create();
    $this->ransomware = Risk::factory()->create([
        'title' => 'Ransomware on payroll', 'owner_id' => $this->risks->owner->id,
        'inherent_likelihood' => 5, 'inherent_impact' => 4, 'residual_likelihood' => 2, 'residual_impact' => 2,
        'treatment' => 'mitigate', 'treatment_plan' => 'Offline backups', 'next_review_on' => today()->subDay(),
    ]);
    app(Modules\Risk\Application\Actions\ManageRiskAssets::class)->add($this->ransomware, Modules\Risk\Domain\Enums\AssetType::Application, $this->risks->payrollApp->id, $this->risks->manager);
});

it('shows the heat map before and after treatment', function (): void {
    $this->actingAs($this->risks->manager);
    Livewire::test(RiskIndex::class)
        ->assertSeeHtml('aria-label="Likelihood 2, impact 2: 1 risk(s)"')
        ->set('map', 'inherent')->assertSeeHtml('aria-label="Likelihood 5, impact 4: 1 risk(s)"')
        ->call('selectCell', 5, 4)->assertSee('Ransomware on payroll')
        ->set('map', 'current')->assertSet('cell', '');
});

it('lists the open risks of an application on its page, for those who may read them', function (): void {
    $this->actingAs($this->risks->lead);
    Livewire::test(ShowApplication::class, ['application' => $this->risks->payrollApp])
        ->assertSee('Open risks')->assertSee('R-0001 · Ransomware on payroll')->assertSee('Low · 4');

    // Agents read applications, not the risk register.
    $this->actingAs($this->risks->agent);
    Livewire::test(ShowApplication::class, ['application' => $this->risks->payrollApp])->assertDontSee('Ransomware on payroll');
});

it('exports the register with actions and acceptances for those who read it', function (): void {
    app(ManageRiskActions::class)->add($this->ransomware, 'Test a restore', $this->risks->lead->id, today()->subDays(3)->toImmutable(), $this->risks->manager);
    $export = Export::factory()->create(['type' => RiskRegisterExport::TYPE, 'user_id' => $this->risks->manager->id]);

    [$risks, $actions] = app(RiskRegisterExport::class)->sheets($export);
    $row = array_combine($risks->headings, iterator_to_array($risks->rows, false)[0]);
    expect($row)->toMatchArray(['Reference' => 'R-0001', 'Concerns' => 'Application: Payroll', 'Inherent risk' => 20, 'Inherent level' => 'Critical', 'Treatment option' => 'Mitigate', 'Residual risk' => 4, 'Residual level' => 'Low'])
        ->and(iterator_to_array($actions->rows, false)[0])->toMatchArray([0 => 'R-0001', 1 => 'Test a restore', 5 => true]);

    expect(fn () => app(RequestExport::class)->handle($this->risks->employee, RiskRegisterExport::TYPE, ExportFormat::Xlsx, []))->toThrow(AuthorizationException::class);
});

it('prints the risk report and renders it as PDF through the PDF service', function (): void {
    $this->actingAs($this->risks->lead);
    $this->get(route('risk.report'))->assertOk()->assertSee('Risk report')->assertSee('Before treatment')->assertSee('R-0001 · Ransomware on payroll')->assertSee('Offline backups')->assertDontSee('Download PDF');

    config(['core.pdf.gotenberg_url' => 'http://gotenberg:3000']);
    Http::fake(['gotenberg:3000/*' => Http::response('%PDF-1.7 fake', 200)]);
    $this->get(route('risk.report.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->body(), 'Ransomware on payroll'));
    expect(AuditLog::query()->where('event', 'exported')->where('new_values->report', 'risk')->exists())->toBeTrue();

    $this->actingAs($this->risks->employee);
    $this->get(route('risk.report'))->assertForbidden();
});

it('emails risk managers what needs attention each week', function (): void {
    app(ManageRiskActions::class)->add($this->ransomware, 'Test a restore', null, today()->subDays(3)->toImmutable(), $this->risks->manager);

    expect(app(SendRiskDigest::class)->figures(today()->toImmutable()))->toMatchArray(['overdue_actions' => 1, 'reviews_overdue' => 1, 'pending_acceptances' => 0]);
    expect(app(SendRiskDigest::class)->handle())->toBe(1);
    Notification::assertSentTo($this->risks->manager, RiskDigest::class, fn (RiskDigest $digest): bool => ($digest->figures['overdue_actions'] ?? 0) === 1 && ! isset($digest->figures['pending_acceptances']));
    Notification::assertNotSentTo($this->risks->lead, RiskDigest::class);
});

it('counts the assessed risks concerning an application, whoever asks', function (): void {
    $unassessed = Risk::factory()->create(['title' => 'Not assessed yet', 'owner_id' => $this->risks->owner->id]);
    app(Modules\Risk\Application\Actions\ManageRiskAssets::class)->add($unassessed, Modules\Risk\Domain\Enums\AssetType::Application, $this->risks->payrollApp->id, $this->risks->manager);

    expect(app(Modules\Core\Domain\Links\Risks::class)->assessedCount('application', $this->risks->payrollApp->id))->toBe(1)
        ->and(app(Modules\Core\Domain\Links\Risks::class)->assessedCount('application', (string) Illuminate\Support\Str::uuid7()))->toBe(0);
});
