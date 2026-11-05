<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Http\Livewire\ApplicationIndex;
use Modules\Applications\Http\Livewire\ShowApplication;
use Modules\Applications\Tests\Support\ApplicationsScenario;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    Notification::fake();
    $this->apps = ApplicationsScenario::create();
    app(OrganizationContext::class)->set($this->apps->organization);
    Livewire::actingAs($this->apps->manager)->test(ShowApplication::class, ['application' => $this->apps->ticketing])->call('startSheet');
    Sheet::query()->sole()->forceFill([
        'status' => SheetStatus::Approved, 'major' => 1, 'minor' => 0, 'approved_at' => now(),
        'content' => ['1_3' => ['exposure' => ['internet']], '2_1' => ['classification' => 'confidential'], '5_1' => ['hosting_model' => 'saas']],
    ])->save();
    $this->undocumented = Application::factory()->create(['name' => 'Second application']);
    $this->actingAs($this->apps->manager);
});

it('shows fields of the version in force, and a dash without one', function (): void {
    Livewire::test(ApplicationIndex::class)
        ->assertSee('Internet-facing')->assertSee('SaaS')->assertSee('Confidential')
        ->assertSee('Second application');
});

it('filters by exposure, hosting and classification, leaving out what has no version in force', function (string $filter, string $match, string $other): void {
    Livewire::test(ApplicationIndex::class)->set($filter, $match)
        ->assertSee($this->apps->ticketing->name)->assertDontSee('Second application');
    Livewire::test(ApplicationIndex::class)->set($filter, $other)
        ->assertDontSee($this->apps->ticketing->name.'</a>', false);
})->with([
    'exposure' => ['exposure', 'internet', 'internal'],
    'hosting' => ['hosting', 'saas', 'hybrid'],
    'classification' => ['classification', 'confidential', 'public'],
]);

it('ignores drafts: only the version in force counts', function (): void {
    $draft = Sheet::query()->sole()->replicate()->forceFill(['status' => SheetStatus::Draft, 'major' => null, 'minor' => null, 'approved_at' => null, 'content' => ['2_1' => ['classification' => 'public']]]);
    $draft->save();

    Livewire::test(ApplicationIndex::class)->set('classification', 'public')->assertDontSee($this->apps->ticketing->name.'</a>', false);
});

it('treats a filter value as data', function (): void {
    Livewire::test(ApplicationIndex::class)->set('hosting', "x' OR '1'='1")->assertDontSee($this->apps->ticketing->name.'</a>', false)->assertOk();
});

it('keeps the number of queries flat as applications grow', function (): void {
    DB::enableQueryLog();
    Livewire::test(ApplicationIndex::class);
    $few = count(DB::getQueryLog());
    Application::factory()->count(5)->create();
    DB::flushQueryLog();
    Livewire::test(ApplicationIndex::class);

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual($few + 2);
});
