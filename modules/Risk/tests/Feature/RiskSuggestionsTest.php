<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Livewire\Livewire;
use Modules\Core\Domain\Risks\RiskSignal;
use Modules\Core\Domain\Risks\RiskSignals;
use Modules\Risk\Application\Suggestions\RefreshSuggestions;
use Modules\Risk\Domain\Enums\SuggestionStatus;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\Suggestion;
use Modules\Risk\Http\Livewire\RiskIndex;
use Modules\Risk\Http\Livewire\SuggestionsPage;
use Modules\Risk\Tests\Support\RiskScenario;

beforeEach(function (): void {
    $this->risks = RiskScenario::create();
    // A test source whose situations the tests switch on and off.
    $this->current = [];
    $this->failing = false;
    app(RiskSignals::class)->register('test', function (): array {
        if ($this->failing) {
            throw new RuntimeException('Source unavailable');
        }

        return $this->current;
    });
});

function testSignal(string $key, RiskScenario $risks): RiskSignal
{
    return new RiskSignal(
        'test.'.$key, 'Test source', 'Unauthorized access to Payroll from the internet', 'Reachable from the internet without MFA.', 'information_security',
        [['type' => 'application', 'id' => $risks->payrollApp->id], ['type' => 'supplier', 'id' => $risks->vendor->id], ['type' => 'process', 'id' => '01999999-0000-7000-8000-000000000000']],
        ['Unauthorized access'], ['Weak authentication or no MFA', 'Not in the catalog'], 4, 4, 'https://toolkit.example/applications/1', $risks->owner->id,
    );
}

it('opens, keeps, resolves and reopens suggestions as situations come and go', function (): void {
    $refresh = app(RefreshSuggestions::class);
    $this->current = [testSignal('a', $this->risks), testSignal('b', $this->risks)];

    // The modules' own sources suggest risks for the scenario too: only the test source is followed here.
    $refresh->handle();
    expect(Suggestion::query()->where('key', 'like', 'test.%')->where('status', 'open')->count())->toBe(2)
        ->and($refresh->handle()['opened'])->toBe(0);

    // A dismissed situation stays dismissed while it lasts.
    $this->actingAs($this->risks->manager);
    $b = Suggestion::query()->where('key', 'test.b')->sole();
    Livewire::test(SuggestionsPage::class)->call('dismiss', $b->id)->assertHasErrors('reasons.'.$b->id)
        ->set('reasons.'.$b->id, 'Covered by R-0004')->call('dismiss', $b->id)->assertHasNoErrors();

    // A failing source resolves nothing of its own.
    $this->current = [];
    $this->failing = true;
    expect($refresh->handle())->toMatchArray(['resolved' => 0, 'failed' => ['test']]);

    $this->failing = false;
    expect($refresh->handle()['resolved'])->toBe(1);
    expect(Suggestion::query()->where('key', 'test.a')->sole()->status)->toBe(SuggestionStatus::Resolved)
        ->and($b->fresh()?->status)->toBe(SuggestionStatus::Dismissed);

    $this->current = [testSignal('a', $this->risks), testSignal('b', $this->risks)];
    expect($refresh->handle()['opened'])->toBe(1)
        ->and(Suggestion::query()->where('key', 'test.a')->sole()->status)->toBe(SuggestionStatus::Open)
        ->and($b->fresh()?->status)->toBe(SuggestionStatus::Dismissed);
});

it('turns a suggestion into a prefilled risk for risk managers only', function (): void {
    $this->current = [testSignal('a', $this->risks)];
    app(RefreshSuggestions::class)->handle();
    $suggestion = Suggestion::query()->where('key', 'test.a')->sole();

    $this->actingAs($this->risks->lead);
    Livewire::test(SuggestionsPage::class)->assertForbidden();

    $this->actingAs($this->risks->manager);
    Livewire::test(RiskIndex::class)->assertSee('Suggested risks ('.Suggestion::query()->where('status', 'open')->count().')');
    Livewire::test(SuggestionsPage::class)
        ->assertSee('Unauthorized access to Payroll from the internet')->assertSee('Payroll Outsourcing SRL')
        ->call('convert', $suggestion->id)
        ->assertRedirect();

    $risk = Risk::query()->sole();
    expect($risk->owner_id)->toBe($this->risks->owner->id)->and($risk->inherentScore())->toBe(16)
        ->and(collect($risk->assets())->pluck('type')->sort()->values()->all())->toBe(['application', 'supplier'])
        ->and($risk->catalogEntries()->pluck('name')->sort()->values()->all())->toBe(['Unauthorized access', 'Weak authentication or no MFA'])
        ->and($suggestion->fresh()?->status)->toBe(SuggestionStatus::Converted)
        ->and($suggestion->fresh()?->risk_id)->toBe($risk->id);

    Livewire::test(SuggestionsPage::class)->call('convert', $suggestion->id)->assertHasErrors('owners.'.$suggestion->id);
    expect(Risk::query()->count())->toBe(1);
});
