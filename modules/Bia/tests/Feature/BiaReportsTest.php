<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Bia\Application\Actions\DecideAssessment;
use Modules\Bia\Application\Actions\ManageDependencies;
use Modules\Bia\Application\Actions\SaveAssessment;
use Modules\Bia\Application\Actions\StartAssessment;
use Modules\Bia\Application\Actions\SubmitAssessment;
use Modules\Bia\Application\Exports\BiaExport;
use Modules\Bia\Application\Notifications\CoordinatorDigest;
use Modules\Bia\Application\Notifications\ReviewDue;
use Modules\Bia\Application\Reviews\BiaSummary;
use Modules\Bia\Application\Reviews\SendCoordinatorDigest;
use Modules\Bia\Application\Reviews\SendReviewReminders;
use Modules\Bia\Domain\Enums\DependencyKind;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Http\Livewire\ProcessIndex;
use Modules\Bia\Tests\Support\BiaScenario;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Supplier;

beforeEach(function (): void {
    Notification::fake();
    config(['bia.reviews.reminder_days' => 30]);
    $this->bia = BiaScenario::create();
});

function approvePayroll(BiaScenario $bia): Assessment
{
    $assessment = app(StartAssessment::class)->handle($bia->payroll, $bia->owner);
    app(SaveAssessment::class)->handle($assessment, $bia->scores(1440), ['rto_minutes' => 240, 'rpo_minutes' => 60, 'workarounds' => 'Paper timesheets']);
    app(ManageDependencies::class)->add($assessment, DependencyKind::Supplier, ['supplier_id' => Supplier::factory()->create(['name' => 'Payroll Outsourcing SRL'])->id], $bia->owner);
    app(SubmitAssessment::class)->handle($assessment, $bia->owner);
    app(DecideAssessment::class)->handle($assessment, $bia->coordinator, true, null);

    return $assessment->fresh();
}

it('reminds the owner and deputy once before a review is due, and again for a new date', function (): void {
    approvePayroll($this->bia);
    $this->bia->payroll->forceFill(['next_review_on' => '2026-07-10'])->save();
    $remind = app(SendReviewReminders::class);

    expect($remind->handle(CarbonImmutable::parse('2026-06-01')))->toBe(0)
        ->and($remind->handle(CarbonImmutable::parse('2026-06-15')))->toBe(1)
        ->and($remind->handle(CarbonImmutable::parse('2026-06-16')))->toBe(0);
    Notification::assertSentTo([$this->bia->owner, $this->bia->deputy], ReviewDue::class, fn (ReviewDue $mail): bool => $mail->dueOn === '2026-07-10' && $mail->process === 'Payroll');
    Notification::assertNotSentTo($this->bia->coordinator, ReviewDue::class);

    $this->bia->payroll->forceFill(['next_review_on' => '2026-07-01'])->save();
    expect($remind->handle(CarbonImmutable::parse('2026-06-16')))->toBe(1);
});

it('does not remind about a process already under review', function (): void {
    approvePayroll($this->bia);
    $this->bia->payroll->forceFill(['next_review_on' => now()->addDays(5)->toDateString()])->save();
    app(StartAssessment::class)->handle($this->bia->payroll, $this->bia->owner);

    expect(app(SendReviewReminders::class)->handle())->toBe(0);
    Notification::assertNotSentTo([$this->bia->owner, $this->bia->deputy], ReviewDue::class);
});

it('sends coordinators a weekly digest only when something needs attention', function (): void {
    // Payroll was never assessed.
    expect(app(SendCoordinatorDigest::class)->handle())->toBe(1);
    Notification::assertSentTo($this->bia->coordinator, CoordinatorDigest::class, fn (CoordinatorDigest $mail): bool => $mail->neverAssessed === 1 && $mail->overdue === 0);
    Notification::assertNotSentTo([$this->bia->owner, $this->bia->lead], CoordinatorDigest::class);

    approvePayroll($this->bia);
    Notification::fake();
    expect(app(SendCoordinatorDigest::class)->handle())->toBe(0);
});

it('sums up the BIA on the process list', function (): void {
    approvePayroll($this->bia);
    BusinessProcess::factory()->create(['name' => 'Invoicing', 'owner_id' => $this->bia->owner->id, 'next_review_on' => '2020-01-01', 'approved_at' => now(), 'rto_minutes' => 1440, 'tier' => 2]);

    $figures = app(BiaSummary::class)->figures(CarbonImmutable::today());
    expect($figures['by_tier'])->toBe([1 => 1, 2 => 1, 3 => 0, 4 => 0])
        ->and($figures['overdue'])->toBe(1)
        ->and($figures['never_assessed'])->toBe(0);

    $this->actingAs($this->bia->coordinator);
    Livewire::test(ProcessIndex::class)->assertSee('Review overdue: 1')->call('export', 'xlsx');
    expect(Export::query()->where('type', BiaExport::TYPE)->exists())->toBeTrue();
});

it('exports processes, dependencies and gaps for people who may see the BIA', function (): void {
    approvePayroll($this->bia);
    $export = Export::factory()->create(['type' => BiaExport::TYPE, 'user_id' => $this->bia->coordinator->id]);

    [$processes, $dependencies, $gaps] = app(BiaExport::class)->sheets($export);
    $row = array_combine($processes->headings, iterator_to_array($processes->rows, false)[0]);

    expect($row)->toMatchArray(['Process' => 'Payroll', 'RTO (minutes)' => 240, 'Tier' => 1, 'Version in force' => 1])
        ->and(iterator_to_array($dependencies->rows, false))->toBe([['Payroll', 'Suppliers', 'Payroll Outsourcing SRL', null]])
        ->and($gaps->headings[0])->toBe('IT system');

    expect(fn () => app(RequestExport::class)->handle($this->bia->agent, BiaExport::TYPE, ExportFormat::Xlsx, []))->toThrow(AuthorizationException::class);
});

it('prints the BIA sheet of a process for the people who may see it', function (): void {
    approvePayroll($this->bia);

    $this->actingAs($this->bia->owner)->get(route('bia.processes.sheet', $this->bia->payroll))
        ->assertOk()
        ->assertSee('Business impact analysis: Payroll')
        ->assertSee('Version 1 (Approved)')
        ->assertSee('Paper timesheets')
        ->assertSee('Payroll Outsourcing SRL')
        ->assertSee('4 hours');

    $this->actingAs($this->bia->employee)->get(route('bia.processes.sheet', $this->bia->payroll))->assertForbidden();
    $this->actingAs($this->bia->employee)->get(route('bia.processes.sheet.pdf', $this->bia->payroll))->assertForbidden();

    // Without the PDF service, back to the page to print it from the browser.
    $this->actingAs($this->bia->owner)->get(route('bia.processes.sheet.pdf', $this->bia->payroll))
        ->assertRedirect(route('bia.processes.sheet', $this->bia->payroll))->assertSessionHas('status');

    config(['core.pdf.gotenberg_url' => 'http://gotenberg:3000']);
    Http::fake(['gotenberg:3000/*' => Http::response('%PDF-1.7 fake', 200)]);
    $this->get(route('bia.processes.sheet', $this->bia->payroll))->assertSee('Download PDF');
    $this->get(route('bia.processes.sheet.pdf', $this->bia->payroll))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->body(), 'Paper timesheets'));
    expect($this->bia->payroll->auditLogs()->where('event', 'exported')->exists())->toBeTrue();
});
