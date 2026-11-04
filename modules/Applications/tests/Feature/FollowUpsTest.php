<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Http\Livewire\ShowApplication;
use Modules\Applications\Tests\Support\ApplicationsScenario;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Http\Livewire\ProcessForm;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Models\SoftwareProduct;
use Modules\Itam\Http\Livewire\LicenseForm;

beforeEach(function (): void {
    Notification::fake();
    $this->apps = ApplicationsScenario::create();
    app(OrganizationContext::class)->set($this->apps->organization);
    Livewire::actingAs($this->apps->manager)->test(ShowApplication::class, ['application' => $this->apps->ticketing])->call('startSheet');
    $this->sheet = Sheet::query()->sole();
    $this->inForce = function (array $content): void {
        $this->sheet->forceFill(['status' => SheetStatus::Approved, 'major' => 1, 'minor' => 0, 'approved_at' => now(), 'content' => $content])->save();
    };
});

function followUpsOf(object $test, ?object $user = null): array
{
    return Livewire::actingAs($user ?? $test->apps->manager)->test(ShowApplication::class, ['application' => $test->apps->ticketing])->viewData('followUps');
}

it('proposes nothing before a version is in force', function (): void {
    expect(followUpsOf($this))->toBe([]);
});

it('proposes a business process when the documentation names none, with the form filled in', function (): void {
    ($this->inForce)(['1_1' => ['description' => 'Tickets']]);
    [$proposal] = followUpsOf($this, $this->apps->member(DefaultRole::Administrator));

    expect($proposal['key'])->toBe('process')->and($proposal['url'])->toContain('/bia/processes/new?name=');
    expect(BusinessProcess::query()->count())->toBe(0);

    Livewire::actingAs($this->apps->member(DefaultRole::Administrator))->withQueryParams(['name' => 'Ticketing'])->test(ProcessForm::class)->assertSet('name', 'Ticketing');
});

it('does not propose a process when the documentation names one', function (): void {
    ($this->inForce)(['10_1' => ['processes' => ['01a1a7cd-3a08-7258-9420-aa72cc000000']]]);

    expect(collect(followUpsOf($this))->pluck('key')->all())->not->toContain('process');
});

it('proposes licences for commercial products the register does not know, and creates nothing', function (): void {
    SoftwareProduct::query()->create(['name' => 'Known Suite']);
    ($this->inForce)(['10_1' => ['processes' => ['x']], '6_6' => ['libraries' => [
        ['name' => 'New Suite', 'license' => 'commercial'],
        ['name' => 'known suite', 'license' => 'commercial'],
        ['name' => 'Free Library', 'license' => 'mit'],
    ]]]);
    $admin = $this->apps->member(DefaultRole::Administrator);
    $proposals = followUpsOf($this, $admin);

    expect($proposals)->toHaveCount(1)->and($proposals[0]['label'])->toContain('New Suite')->and($proposals[0]['url'])->toContain('new_product=New');
    expect(SoftwareProduct::query()->count())->toBe(1);

    Livewire::actingAs($admin)->withQueryParams(['new_product' => 'New Suite'])->test(LicenseForm::class)->assertSet('newProduct', 'New Suite');
});

it('shows no link to someone who may not create there, only what to ask for', function (): void {
    ($this->inForce)(['1_1' => ['description' => 'Tickets']]);
    [$proposal] = followUpsOf($this);

    expect($proposal['url'])->toBeNull();
});

it('hides a dismissed proposal, for good, and records who said so', function (): void {
    ($this->inForce)(['1_1' => ['description' => 'Tickets']]);
    Livewire::actingAs($this->apps->manager)->test(ShowApplication::class, ['application' => $this->apps->ticketing])
        ->call('dismissFollowUp', 'process')->assertOk();

    expect(followUpsOf($this))->toBe([]);
});

it('refuses a made-up key, and people who may not change the application', function (): void {
    ($this->inForce)(['1_1' => ['description' => 'Tickets']]);
    Livewire::actingAs($this->apps->manager)->test(ShowApplication::class, ['application' => $this->apps->ticketing])
        ->call('dismissFollowUp', 'not-a-key')->assertNotFound();

    expect(followUpsOf($this, $this->apps->agent))->toBe([]);
    Livewire::actingAs($this->apps->agent)->test(ShowApplication::class, ['application' => $this->apps->ticketing])
        ->call('dismissFollowUp', 'process')->assertForbidden();
});
