<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Livewire\Livewire;
use Modules\Core\Domain\Events\ApplicationDocumented;
use Modules\Core\Domain\Models\User;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\PageLink;
use Modules\Documentation\Domain\Models\Space;
use Modules\Documentation\Http\Livewire\PageEditor;
use Modules\Documentation\Tests\Support\DocsScenario;

beforeEach(function (): void {
    $this->docs = DocsScenario::create();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function documented(DocsScenario $docs, User $owner, array $overrides = []): ApplicationDocumented
{
    $values = [
        'organizationId' => $docs->organization->id,
        'applicationId' => '01a19d00-0000-7000-8000-000000000001',
        'configurationItemId' => null,
        'code' => 'APP-PAY',
        'name' => 'Payroll',
        'version' => '1.0',
        'description' => 'Pays salaries | monthly *bonus*.',
        'criticality' => 'High',
        'owners' => [['role' => 'Business owner', 'name' => 'Ana Pop'], ['role' => 'IT / application owner', 'name' => $owner->name]],
        'ownerIds' => [$owner->id],
        'support' => [['label' => 'Support e-mail', 'value' => 'payroll@vendor.example']],
        'components' => ['PAYDB01'],
        'processes' => [['name' => 'Monthly payroll', 'rto' => '4 hours', 'rpo' => '1 hour']],
        'applicationUrl' => 'http://localhost/applications/1',
        'documentUrl' => 'http://localhost/applications/sheets/1/document',
        'actor' => $docs->lead,
        ...$overrides,
    ];

    return new ApplicationDocumented(...$values);
}

it('creates the Applications space and the application page with its summary and room for notes', function (): void {
    event(documented($this->docs, $this->docs->engineer));

    $space = Space::query()->where('purpose', Space::APPLICATIONS)->sole();
    $page = Page::query()->sole();
    expect($space->name)->toBe('Applications')->and($space->owners()->pluck('users.id')->all())->toContain($this->docs->engineer->id)
        ->and($page->title)->toBe('APP-PAY · Payroll')->and($page->isPublished())->toBeTrue()
        ->and(PageLink::query()->where('page_id', $page->id)->where('linked_type', 'applications.application')->exists())->toBeTrue();

    $this->actingAs($this->docs->agent);
    $this->get(route('documentation.pages.show', $page))->assertOk()
        ->assertSee('Monthly payroll')->assertSee('4 hours')->assertSee('PAYDB01')->assertSee('payroll@vendor.example')
        // Text from the documentation stays literal, the markers are not shown.
        ->assertSee('Pays salaries | monthly *bonus*.')->assertDontSee('application-summary:start')
        ->assertSee('Notes');
});

it('replaces only the summary at the next version, keeping the team\'s notes', function (): void {
    event(documented($this->docs, $this->docs->engineer));
    $page = Page::query()->sole();

    $this->actingAs($this->docs->engineer);
    Livewire::test(PageEditor::class, ['page' => $page])->set('body', $page->body."\nRestart the payroll service after month-end close.")->call('save')->assertHasNoErrors();

    event(documented($this->docs, $this->docs->engineer, ['version' => '2.0', 'components' => ['PAYDB02']]));
    $page = Page::query()->sole();
    expect($page->body)->toContain('PAYDB02')->not->toContain('PAYDB01')->toContain('Restart the payroll service after month-end close.')
        ->and($page->version)->toBe(3)->and(Page::query()->count())->toBe(1);

    // The same summary again changes nothing.
    event(documented($this->docs, $this->docs->engineer, ['version' => '2.0', 'components' => ['PAYDB02']]));
    expect(Page::query()->sole()->version)->toBe(3);
});

it('publishes the summary at once even when the space requires approval, and adopts an existing Applications space', function (): void {
    $existing = Space::factory()->create(['name' => 'applications', 'requires_approval' => true]);
    event(documented($this->docs, $this->docs->engineer));

    expect(Space::query()->count())->toBe(1)->and($existing->fresh()?->purpose)->toBe(Space::APPLICATIONS)
        ->and(Page::query()->sole()->isPublished())->toBeTrue();
});
