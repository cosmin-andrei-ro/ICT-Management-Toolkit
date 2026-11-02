<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Documentation\Application\Notifications\DocumentationDigest;
use Modules\Documentation\Application\Notifications\PageUpdate;
use Modules\Documentation\Application\Reminders\SendDocumentationDigest;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\Space;
use Modules\Documentation\Http\Livewire\DocsIndex;
use Modules\Documentation\Http\Livewire\PageEditor;
use Modules\Documentation\Http\Livewire\ShowPage;
use Modules\Documentation\Http\Livewire\ShowSpace;
use Modules\Documentation\Http\Livewire\SpaceForm;
use Modules\Documentation\Tests\Support\DocsScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->docs = DocsScenario::create();
    $this->secondOwner = $this->docs->member(DefaultRole::TeamLead);

    $this->actingAs($this->docs->lead);
    Livewire::test(SpaceForm::class)
        ->set('name', 'Procedures')->set('requiresApproval', true)->set('reviewMonths', 6)
        ->set('ownerIds', [$this->docs->lead->id, $this->secondOwner->id])->set('editTeamIds', [$this->docs->network->id])
        ->call('save')->assertHasNoErrors();
    $this->space = Space::query()->where('name', 'Procedures')->sole();
});

function editPage(Page $page, string $body): void
{
    Livewire::test(PageEditor::class, ['page' => $page->fresh()])->set('body', $body)->call('save')->assertHasNoErrors();
}

it('keeps a page of a controlled space from readers until an owner other than its author approves it', function (): void {
    $this->actingAs($this->docs->engineer);
    Livewire::test(PageEditor::class, ['space' => $this->space])->set('title', 'Firewall change procedure')->set('body', 'Step one: open a change.')->call('save')->assertHasNoErrors();
    $page = Page::query()->sole();
    expect($page->isPublished())->toBeFalse();

    // Readers do not see an unpublished page anywhere.
    $this->actingAs($this->docs->agent);
    $this->get(route('documentation.pages.show', $page))->assertForbidden();
    Livewire::test(ShowSpace::class, ['space' => $this->space])->assertDontSee('Firewall change procedure');

    $this->actingAs($this->docs->engineer);
    Livewire::test(ShowPage::class, ['page' => $page])->assertSee('Not published yet')->call('submit');
    Notification::assertSentTo([$this->docs->lead, $this->secondOwner], PageUpdate::class, fn (PageUpdate $update): bool => $update->kind === 'submitted');
    Livewire::test(ShowPage::class, ['page' => $page->fresh()])->call('approve')->assertForbidden();

    $this->actingAs($this->docs->lead);
    Livewire::test(ShowSpace::class, ['space' => $this->space])->assertSee('Waiting for approval');
    Livewire::test(ShowPage::class, ['page' => $page->fresh()])->assertSee('waits for your approval')->call('approve')->assertHasNoErrors();
    $page->refresh();
    expect($page->published_version)->toBe(1)->and($page->review_on?->toDateString())->toBe(CarbonImmutable::today()->addMonthsNoOverflow(6)->toDateString());
    Notification::assertSentTo($this->docs->engineer, PageUpdate::class, fn (PageUpdate $update): bool => $update->kind === 'approved');

    // A new draft stays out of readers' sight and of search until approved.
    $this->actingAs($this->docs->engineer);
    editPage($page, 'Step one: open a change. Step two: secret draft wording.');

    $this->actingAs($this->docs->agent);
    $this->get(route('documentation.pages.show', $page))->assertOk()->assertSee('Step one: open a change.')->assertDontSee('secret draft wording')
        ->assertSee('approved by '.$this->docs->lead->name);
    Livewire::test(DocsIndex::class)->set('q', 'secret')->assertSee('No page found');
    $this->get(route('documentation.pages.history', $page))->assertForbidden();
});

it('lets an owner reject a version with a reason, and never approve their own', function (): void {
    $this->actingAs($this->docs->lead);
    Livewire::test(PageEditor::class, ['space' => $this->space])->set('title', 'Backup policy')->set('body', 'Daily.')->call('save');
    $page = Page::query()->sole();
    Livewire::test(ShowPage::class, ['page' => $page])->call('submit')->call('approve')->assertForbidden();

    $this->actingAs($this->secondOwner);
    Livewire::test(ShowPage::class, ['page' => $page->fresh()])
        ->call('reject')->assertHasErrors('rejectionNote')
        ->set('rejectionNote', 'Say how long backups are kept')->call('reject')->assertHasNoErrors();
    expect($page->fresh()?->pending_version)->toBeNull();
    Notification::assertSentTo($this->docs->lead, PageUpdate::class, fn (PageUpdate $update): bool => $update->kind === 'rejected' && $update->detail === 'Say how long backups are kept');

    $this->actingAs($this->docs->lead);
    Livewire::test(ShowPage::class, ['page' => $page->fresh()])->assertSee('Say how long backups are kept');

    // A newer save withdraws a submission, so nobody approves stale text.
    Livewire::test(ShowPage::class, ['page' => $page->fresh()])->call('submit');
    editPage($page, 'Daily, kept 30 days.');
    expect($page->fresh()?->pending_version)->toBeNull();
});

it('publishes every save at once in a free space, and moves the review date when reviewed', function (): void {
    $this->space->forceFill(['requires_approval' => false])->save();
    $this->actingAs($this->docs->engineer);
    Livewire::test(PageEditor::class, ['space' => $this->space])->set('title', 'VPN')->set('body', 'Client 5.')->call('save');
    $page = Page::query()->sole();
    expect($page->published_version)->toBe(1);

    $page->forceFill(['review_on' => today()->subDay()])->save();
    Livewire::test(ShowPage::class, ['page' => $page->fresh()])->call('markReviewed');
    expect($page->fresh()?->review_on?->toDateString())->toBe(CarbonImmutable::today()->addMonthsNoOverflow(6)->toDateString());
});

it('emails owners what waits for them and what is due for review', function (): void {
    $this->actingAs($this->docs->engineer);
    Livewire::test(PageEditor::class, ['space' => $this->space])->set('title', 'Pending page')->set('body', 'x')->call('save');
    Livewire::test(ShowPage::class, ['page' => Page::query()->sole()])->call('submit');
    Page::factory()->create(['space_id' => $this->space->id, 'title' => 'Old page', 'published_version' => 1, 'published_body' => 'y', 'review_on' => today()->subDays(3)]);

    expect(app(SendDocumentationDigest::class)->figures($this->docs->lead, CarbonImmutable::today()))->toBe(['to_approve' => 1, 'review_overdue' => 1, 'review_due' => 0]);
    expect(app(SendDocumentationDigest::class)->handle())->toBe(2);
    Notification::assertSentTo($this->secondOwner, DocumentationDigest::class);
    Notification::assertNotSentTo($this->docs->agent, DocumentationDigest::class);
});

it('prints the published version with its approval, also as PDF', function (): void {
    $this->actingAs($this->docs->engineer);
    Livewire::test(PageEditor::class, ['space' => $this->space])->set('title', 'Access review')->set('body', 'Quarterly review of **admin** rights.')->call('save');
    $page = Page::query()->sole();
    $this->get(route('documentation.pages.print', $page))->assertNotFound();
    Livewire::test(ShowPage::class, ['page' => $page])->call('submit');
    $this->actingAs($this->secondOwner);
    Livewire::test(ShowPage::class, ['page' => $page->fresh()])->call('approve');

    $this->actingAs($this->docs->agent);
    $this->get(route('documentation.pages.print', $page))->assertOk()->assertSee('<strong>admin</strong>', false)->assertSee('approved by '.$this->secondOwner->name);
    config(['core.pdf.gotenberg_url' => 'http://gotenberg:3000']);
    Http::fake(['gotenberg:3000/*' => Http::response('%PDF-1.7 fake', 200)]);
    $this->get(route('documentation.pages.pdf', $page))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->body(), 'Quarterly review'));
    $this->actingAs($this->docs->employee);
    $this->get(route('documentation.pages.pdf', $page))->assertForbidden();
});
