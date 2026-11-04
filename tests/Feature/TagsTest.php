<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Modules\Applications\Domain\Models\Application;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\Tag;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\Tags\Pages\ManageTagList;
use Modules\Core\Http\Livewire\Tags\RecordTags;
use Modules\Core\Http\Middleware\RequireTwoFactor;
use Modules\Itam\Domain\Models\Asset;
use Modules\Projects\Domain\Models\Project;
use Modules\Risk\Domain\Models\Risk;
use Modules\Ticketing\Domain\Models\Ticket;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->organization = Organization::query()->sole();
    app(OrganizationContext::class)->set($this->organization);
    $this->withoutMiddleware(RequireTwoFactor::class);
    $this->admin = User::query()->where('email', 'admin@example.com')->sole();
    $this->agent = User::query()->where('email', 'agent@example.com')->sole();
    $this->employee = User::query()->where('email', 'employee@example.com')->sole();
    $this->item = ConfigurationItem::query()->where('name', 'ERP')->sole();
});

it('lets someone who may change a record tag it, with one spelling, and records it in its history', function (): void {
    Livewire::actingAs($this->agent)->test(RecordTags::class, ['record' => $this->item])
        ->set('name', '  Needs   Review ')->call('add')->assertHasNoErrors()->assertSee('needs review')->assertSet('name', '')
        ->set('name', 'needs review')->call('add')->assertHasNoErrors();

    expect($this->item->tags()->pluck('name')->all())->toBe(['needs review'])
        ->and(Tag::query()->where('name', 'needs review')->count())->toBe(1);

    $entry = AuditLog::query()->where('auditable_id', $this->item->id)->where('event', AuditEvent::Updated->value)->latest('created_at')->first();
    expect($entry?->new_values)->toBe(['tags' => ['needs review']]);

    $tag = Tag::query()->where('name', 'needs review')->sole();
    Livewire::actingAs($this->agent)->test(RecordTags::class, ['record' => $this->item])->call('remove', $tag->id);
    expect($this->item->tags()->count())->toBe(0);
});

it('works on each kind of record', function (string $class, array $open): void {
    // Closed projects and tickets cannot be changed, so they are not what this test is about.
    $record = $class::query()->where($open)->firstOrFail();
    Livewire::actingAs($this->admin)->test(RecordTags::class, ['record' => $record, 'ability' => $class === Ticket::class ? 'work' : 'update'])
        ->set('name', 'audit-2026')->call('add')->assertHasNoErrors();

    expect($record->tags()->pluck('name')->all())->toBe(['audit-2026']);
    $response = $this->actingAs($this->admin)->get(route('core.tags.show', Tag::query()->where('name', 'audit-2026')->sole()))->assertOk();
    $response->assertDontSee(__('core::tags.page_none'));
})->with([
    'ticket' => [Ticket::class, ['closed_at' => null]],
    'configuration item' => [ConfigurationItem::class, []],
    'asset' => [Asset::class, []],
    'application' => [Application::class, []],
    'risk' => [Risk::class, ['status' => 'open']],
    'project' => [Project::class, ['status' => 'active']],
    'supplier' => [Supplier::class, []],
]);

it('refuses invalid names and too many tags', function (): void {
    $component = Livewire::actingAs($this->agent)->test(RecordTags::class, ['record' => $this->item]);
    $component->set('name', '<script>')->call('add')->assertHasErrors('name');
    $component->set('name', str_repeat('a', 51))->call('add')->assertHasErrors('name');

    foreach (range(1, 20) as $number) {
        $component->set('name', "tag {$number}")->call('add')->assertHasNoErrors();
    }

    $component->set('name', 'one more')->call('add')->assertHasErrors('name');
    expect($this->item->tags()->count())->toBe(20);
});

it('does not let someone change the tags of a record they may not change', function (): void {
    $auditor = User::query()->where('email', 'auditor@example.com')->sole();

    $supplier = Supplier::query()->firstOrFail();

    Livewire::actingAs($auditor)->test(RecordTags::class, ['record' => $supplier])
        ->assertOk()->assertDontSee(__('core::tags.add_label'))
        ->set('name', 'nope')->call('add')->assertForbidden();
    expect($supplier->tags()->count())->toBe(0);
});

it('keeps tags from requesters', function (): void {
    $this->item->tags()->attach(Tag::query()->create(['name' => 'breach-2026'])->id);
    $tag = Tag::query()->where('name', 'breach-2026')->sole();

    $this->actingAs($this->employee)->get(route('core.tags.show', $tag))->assertForbidden();
    Livewire::actingAs($this->employee)->test(RecordTags::class, ['record' => $this->item])->assertForbidden();
    expect(app(Modules\Core\Domain\Links\LinkedRecords::class)->searchEverywhere('breach', $this->employee))->toBe([]);
});

it('shows on a tag\'s page only what the person may see', function (): void {
    $risk = Risk::query()->orderBy('title')->firstOrFail();
    $tag = Tag::query()->create(['name' => 'mixed']);
    $this->item->tags()->attach($tag->id);
    $risk->tags()->attach($tag->id);
    $agent = User::query()->where('email', 'cab@example.com')->sole();

    $this->actingAs($this->admin)->get(route('core.tags.show', $tag))->assertOk()->assertSee('ERP')->assertSee($risk->title);
    $this->actingAs($agent)->get(route('core.tags.show', $tag))->assertOk()->assertDontSee($risk->title);
});

it('keeps organizations apart', function (): void {
    $tag = Tag::query()->create(['name' => 'ours']);
    $other = Organization::factory()->create();
    $this->admin->organizations()->attach($other);
    $this->admin->forceFill(['current_organization_id' => $other->id])->saveQuietly();

    $this->actingAs($this->admin)->get(route('core.tags.show', $tag->id))->assertNotFound();
});

it('finds tags in the global search', function (): void {
    Tag::query()->create(['name' => 'quarterly review']);

    expect(app(Modules\Core\Domain\Links\LinkedRecords::class)->searchEverywhere('QUARTERLY', $this->admin))->toHaveKey(__('core::tags.linked_kind'));
});

it('lets administrators tidy the list, and only them', function (): void {
    $tag = Tag::query()->create(['name' => 'mispelt']);
    Tag::query()->create(['name' => 'taken']);
    $this->item->tags()->attach($tag->id);

    Livewire::actingAs($this->admin)->test(ManageTagList::class)
        ->assertCanSeeTableRecords([$tag])
        ->callAction(TestAction::make('edit')->table($tag), ['name' => 'taken'])->assertHasFormErrors(['name']);
    Livewire::actingAs($this->admin)->test(ManageTagList::class)
        ->callAction(TestAction::make('edit')->table($tag), ['name' => 'Misspelt'])->assertHasNoFormErrors();
    expect($tag->fresh()->name)->toBe('misspelt');

    Livewire::actingAs($this->admin)->test(ManageTagList::class)->callAction(TestAction::make('delete')->table($tag));
    expect(Tag::query()->whereKey($tag->id)->exists())->toBeFalse()->and($this->item->tags()->count())->toBe(0);

    $this->actingAs($this->agent)->get('/admin/tags')->assertForbidden();
});
