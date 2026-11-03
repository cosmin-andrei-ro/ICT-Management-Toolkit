<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Livewire\Livewire;
use Modules\Audit\Application\Actions\AddFramework;
use Modules\Audit\Application\Frameworks\Coverage;
use Modules\Audit\Application\Frameworks\FrameworkCatalog;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Audit\Http\Livewire\ComplianceIndex;
use Modules\Audit\Http\Livewire\ControlForm;
use Modules\Audit\Http\Livewire\ControlIndex;
use Modules\Audit\Http\Livewire\MyControls;
use Modules\Audit\Http\Livewire\ShowControl;
use Modules\Audit\Http\Livewire\ShowFramework;
use Modules\Audit\Tests\Support\ComplianceScenario;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Navigation\Navigation;

beforeEach(function (): void {
    $this->compliance = ComplianceScenario::create();
});

function requirementOf(string $key, string $reference): Requirement
{
    return Requirement::query()->whereRelation('framework', 'key', $key)->where('reference', $reference)->sole();
}

it('ships the four frameworks with unique references and no empty titles', function (string $key, int $count): void {
    $framework = FrameworkCatalog::get($key);
    $references = array_column($framework['requirements'], 'reference');

    expect($references)->toHaveCount($count)
        ->and(array_unique($references))->toHaveCount($count)
        ->and(array_filter(array_column($framework['requirements'], 'title'), fn (string $title): bool => trim($title) === ''))->toBe([]);
})->with([
    'ISO/IEC 27001 Annex A' => ['iso27001', 93],
    'NIS2' => ['nis2', count(FrameworkCatalog::get('nis2')['requirements'])],
    'GDPR' => ['gdpr', count(FrameworkCatalog::get('gdpr')['requirements'])],
    'DORA' => ['dora', count(FrameworkCatalog::get('dora')['requirements'])],
]);

it('lets compliance managers add frameworks from the toolkit or their own, once', function (): void {
    $this->actingAs($this->compliance->manager);
    Livewire::test(ComplianceIndex::class)->set('catalogKey', 'iso27001')->call('addFromCatalog')->assertHasNoErrors()->assertRedirect();

    $iso = Framework::query()->where('key', 'iso27001')->sole();
    expect($iso->has_soa)->toBeTrue()->and($iso->requirements()->count())->toBe(93)
        ->and(requirementOf('iso27001', '8.5')->group)->toBe('Technological controls');

    Livewire::test(ComplianceIndex::class)->assertDontSeeHtml('value="iso27001"')->set('catalogKey', 'iso27001')->call('addFromCatalog')->assertHasErrors('catalogKey');
    expect(fn () => app(AddFramework::class)->fromCatalog('iso27001'))->toThrow(InvalidArgumentException::class);

    Livewire::test(ComplianceIndex::class)->set('customName', 'Customer security requirements')->set('customVersion', '2026')->call('addCustom')->assertHasNoErrors();
    $custom = Framework::query()->where('name', 'Customer security requirements')->sole();
    Livewire::test(ComplianceIndex::class)->set('customName', 'Customer security requirements')->call('addCustom')->assertHasErrors('customName');

    Livewire::test(ShowFramework::class, ['framework' => $custom])
        ->set('newReference', 'CS-1')->set('newTitle', 'Encrypt customer data at rest')->set('newGroup', 'Data')->call('addRequirement')->assertHasNoErrors()
        ->set('newReference', 'CS-1')->set('newTitle', 'Duplicate')->call('addRequirement')->assertHasErrors('newReference')
        ->assertSee('Encrypt customer data at rest');
    expect($custom->requirements()->pluck('reference')->all())->toBe(['CS-1'])
        ->and(AuditLog::query()->where('auditable_id', $iso->id)->exists())->toBeTrue();

    $this->actingAs($this->compliance->auditor);
    Livewire::test(ComplianceIndex::class)->assertSee('ISO/IEC 27001')->assertDontSee('Add a framework')->set('catalogKey', 'nis2')->call('addFromCatalog')->assertForbidden();
    Livewire::test(ShowFramework::class, ['framework' => $custom])->set('newReference', 'CS-2')->set('newTitle', 'x')->call('addRequirement')->assertForbidden();
});

it('records applicability with a justification when a requirement does not apply', function (): void {
    app(AddFramework::class)->fromCatalog('iso27001');
    $iso = Framework::query()->sole();
    $physical = requirementOf('iso27001', '7.4');

    $this->actingAs($this->compliance->manager);
    $page = Livewire::test(ShowFramework::class, ['framework' => $iso])
        ->call('decide', $physical->id)
        ->set('applicability', 'no')
        ->call('saveDecision')->assertHasErrors('justification');
    $page->set('justification', 'No premises of our own: offices in a shared building secured by the landlord.')
        ->call('saveDecision')->assertHasNoErrors()
        ->assertSet('decidingId', null)
        ->assertSee('Not applicable');

    $physical->refresh();
    expect($physical->is_applicable)->toBeFalse()->and($physical->updated_by)->toBe($this->compliance->manager->id)
        ->and(AuditLog::query()->where('auditable_id', $physical->id)->exists())->toBeTrue();
    expect(app(Coverage::class)->of($iso))->toMatchArray(['total' => 93, 'applicable' => 92, 'implemented_percent' => 0]);

    $this->actingAs($this->compliance->auditor);
    Livewire::test(ShowFramework::class, ['framework' => $iso])->assertSee('No premises of our own')->call('decide', $physical->id)->assertForbidden();
});

it('maps one control to requirements of several frameworks and rolls their state up', function (): void {
    foreach (['iso27001', 'nis2', 'dora'] as $key) {
        app(AddFramework::class)->fromCatalog($key);
    }

    $this->actingAs($this->compliance->manager);
    Livewire::test(ControlForm::class)
        ->set('name', 'Multifactor authentication on remote access')
        ->set('ownerId', $this->compliance->owner->id)
        ->call('save')->assertHasNoErrors();
    Livewire::test(ControlForm::class)->set('name', 'multifactor authentication on remote access')->set('ownerId', $this->compliance->owner->id)->call('save')->assertHasErrors('name');
    $control = Control::query()->sole();
    expect($control->code())->toBe('C-001');

    $iso = requirementOf('iso27001', '8.5');
    $nis2 = requirementOf('nis2', 'Art. 21(2)(j)');
    $dora = requirementOf('dora', 'Art. 9');

    $page = Livewire::test(ShowControl::class, ['control' => $control])->set('mapFrameworkId', $iso->framework_id)->set('mapSearch', 'authentication')->assertSee($iso->title);
    foreach ([$iso, $nis2, $dora] as $requirement) {
        $page->call('mapRequirement', $requirement->id)->assertHasNoErrors();
    }
    $page->assertSee('Art. 9');
    expect($control->requirements()->count())->toBe(3)->and($iso->fresh()?->state()->value)->toBe('planned');

    // Its owner, a plain employee, finds it and reports it implemented; the requirements follow.
    $this->actingAs($this->compliance->owner);
    Livewire::test(MyControls::class)->assertSee('C-001');
    Livewire::test(ShowControl::class, ['control' => $control])
        ->set('status', 'implemented')->set('notes', 'Enforced by the VPN and the identity provider')
        ->call('saveImplementation')->assertHasNoErrors()->assertSet('implementationSaved', true)
        ->call('mapRequirement', requirementOf('iso27001', '5.15')->id)->assertForbidden();
    Livewire::test(ControlForm::class, ['control' => $control])->assertForbidden();

    expect($iso->fresh()?->state()->value)->toBe('implemented')->and($dora->fresh()?->state()->value)->toBe('implemented')
        ->and(app(Coverage::class)->of($iso->framework)['states']['implemented'])->toBe(1)
        ->and(AuditLog::query()->where('auditable_id', $control->id)->count())->toBeGreaterThanOrEqual(4);

    // Removing a mapping and deleting the control leave the requirements uncovered.
    $this->actingAs($this->compliance->manager);
    Livewire::test(ShowControl::class, ['control' => $control])->call('unmapRequirement', $dora->id);
    expect($dora->fresh()?->controls)->toHaveCount(0);
    Livewire::test(ShowControl::class, ['control' => $control])->call('delete')->assertRedirect(route('audit.controls.index'));
    expect($iso->fresh()?->state()->value)->toBe('undecided');
});

it('shows compliance in the menu of its readers, and owners their controls', function (): void {
    Control::factory()->create(['owner_id' => $this->compliance->owner->id]);
    $menu = fn ($user): array => array_map(fn (array $entry): string|array => isset($entry['items']) ? [$entry['label'] => array_column($entry['items'], 'label')] : $entry['label'], app(Navigation::class)->for($user));

    expect($menu($this->compliance->auditor))->toContain(['Compliance & audit' => ['Compliance', 'Controls', 'Audits', 'Findings', 'Audit log']])
        ->and($menu($this->compliance->owner))->toContain('My compliance tasks')
        ->and($menu($this->compliance->employee))->not->toContain('My compliance tasks');
});

it('lets auditors and security officers read, and keeps everyone else and other organizations out', function (): void {
    app(AddFramework::class)->fromCatalog('gdpr');
    $gdpr = Framework::query()->sole();
    $control = Control::factory()->create(['name' => 'Records of processing kept up to date', 'owner_id' => $this->compliance->owner->id]);

    foreach ([$this->compliance->auditor, $this->compliance->security] as $reader) {
        $this->actingAs($reader);
        $this->get(route('audit.index'))->assertOk()->assertSee('GDPR');
        $this->get(route('audit.frameworks.show', $gdpr))->assertOk();
        $this->get(route('audit.controls.index'))->assertOk()->assertSee('C-001')->assertDontSee('New control');
        Livewire::test(ShowControl::class, ['control' => $control])->assertDontSee('Delete control')->call('saveImplementation')->assertForbidden();
        Livewire::test(ControlIndex::class)->set('unmapped', true)->assertSee('Records of processing');
    }

    $this->actingAs($this->compliance->employee);
    $this->get(route('audit.index'))->assertForbidden();
    $this->get(route('audit.frameworks.show', $gdpr))->assertForbidden();
    $this->get(route('audit.controls.index'))->assertForbidden();
    $this->get(route('audit.controls.show', $control))->assertForbidden();
    $this->get(route('audit.controls.create'))->assertForbidden();

    // Another organization's frameworks and controls are out of reach, and cannot be mapped.
    app(OrganizationContext::class)->set(Organization::factory()->create());
    app(AddFramework::class)->fromCatalog('gdpr');
    $foreign = Framework::query()->sole();
    $foreignControl = Control::factory()->create(['name' => 'Foreign control']);
    expect($foreignControl->code())->toBe('C-001');

    app(OrganizationContext::class)->set($this->compliance->organization);
    $this->actingAs($this->compliance->manager);
    $this->get(route('audit.frameworks.show', $foreign))->assertNotFound();
    $this->get(route('audit.controls.show', $foreignControl))->assertNotFound();
    Livewire::test(ShowControl::class, ['control' => $control])
        ->call('mapRequirement', Requirement::query()->withoutGlobalScopes()->where('framework_id', $foreign->id)->value('id'))
        ->assertHasErrors('mapSearch');
    expect($control->requirements()->count())->toBe(0);
});
