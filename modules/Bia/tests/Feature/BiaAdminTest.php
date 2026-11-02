<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BiaSettings;
use Modules\Bia\Domain\Models\ImpactCategory;
use Modules\Bia\Domain\Models\ImpactScore;
use Modules\Bia\Domain\Models\TimeHorizon;
use Modules\Bia\Http\Filament\Pages\BiaSettingsPage;
use Modules\Bia\Http\Filament\Resources\ImpactCategories\Pages\ManageImpactCategories;
use Modules\Bia\Http\Filament\Resources\TimeHorizons\Pages\ManageTimeHorizons;
use Modules\Bia\Tests\Support\BiaScenario;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Http\Filament\Resources\Departments\Pages\ManageDepartments;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->bia = BiaScenario::create();
    $this->actingAs($this->bia->coordinator);
});

it('gives each organization the default matrix once', function (): void {
    expect(ImpactCategory::query()->count())->toBe(5)
        ->and(TimeHorizon::query()->orderBy('minutes')->pluck('minutes')->all())->toBe([240, 1440, 4320, 10080, 20160, 43200])
        ->and(ImpactCategory::query()->where('name', 'Financial')->sole()->levelDescription(5))->toBe('Loss threatening the organization\'s viability');

    ImpactCategory::query()->where('name', 'Reputation')->delete();
    $this->get('/admin/bia/impact-categories')->assertOk();
    expect(ImpactCategory::query()->count())->toBe(4);
});

it('lets the coordinator edit categories with their level descriptions', function (): void {
    Livewire::test(ManageImpactCategories::class)
        ->callAction('create', data: ['name' => 'Health and safety', 'sort_order' => 6, 'is_active' => true, 'levels' => ['None', 'First aid', 'Injury', 'Serious injury', 'Death']])
        ->assertHasNoFormErrors();

    expect(ImpactCategory::query()->where('name', 'Health and safety')->sole()->levels)->toBe(['None', 'First aid', 'Injury', 'Serious injury', 'Death']);
});

it('fixes a horizon\'s duration once assessments use it', function (): void {
    $day = TimeHorizon::query()->where('minutes', 1440)->sole();
    $assessment = Assessment::factory()->create(['process_id' => $this->bia->payroll->id]);
    ImpactScore::query()->insert(['assessment_id' => $assessment->id, 'organization_id' => $assessment->organization_id, 'category_id' => ImpactCategory::query()->first()->id, 'horizon_id' => $day->id, 'score' => 3]);

    Livewire::test(ManageTimeHorizons::class)
        ->assertTableActionHidden('delete', $day)
        ->mountTableAction('edit', $day)
        ->assertFormFieldIsDisabled('minutes');
});

it('saves the unacceptable level and review interval for the organization', function (): void {
    Livewire::test(BiaSettingsPage::class)
        ->fillForm(['unacceptable_level' => 6, 'review_months' => 12])->call('save')->assertHasFormErrors(['unacceptable_level'])
        ->fillForm(['unacceptable_level' => 3, 'review_months' => 6])->call('save')->assertHasNoFormErrors();

    expect(BiaSettings::current()->unacceptable_level)->toBe(3)->and(BiaSettings::current()->review_months)->toBe(6);
});

it('lets the coordinator keep departments, and keeps agents and team leads out of BIA settings', function (): void {
    $finance = Department::query()->where('name', 'Finance')->sole();
    Livewire::test(ManageDepartments::class)
        ->callAction('create', data: ['name' => 'Payroll office', 'parent_id' => $finance->id])->assertHasNoFormErrors();
    Livewire::test(ManageDepartments::class)
        ->callTableAction('edit', $finance, data: ['name' => 'Finance', 'parent_id' => Department::query()->where('name', 'Payroll office')->sole()->id])
        ->assertHasTableActionErrors(['parent_id']);

    $this->actingAs($this->bia->lead)->get('/admin/bia/settings')->assertForbidden();
    $this->actingAs($this->bia->lead)->get('/admin/bia/impact-categories')->assertForbidden();
    $this->actingAs($this->bia->agent)->get('/admin')->assertForbidden();
});
