<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\PageVersion;
use Modules\Documentation\Domain\Models\Space;
use Modules\Documentation\Http\Livewire\DocsIndex;
use Modules\Documentation\Http\Livewire\PageEditor;
use Modules\Documentation\Http\Livewire\PageHistory;
use Modules\Documentation\Http\Livewire\ShowPage;
use Modules\Documentation\Http\Livewire\SpaceForm;
use Modules\Documentation\Tests\Support\DocsScenario;

beforeEach(function (): void {
    Storage::fake('attachments');
    $this->docs = DocsScenario::create();
});

function networkSpace(DocsScenario $docs, string $visibility = 'staff', array $readTeams = []): Space
{
    test()->actingAs($docs->lead);
    Livewire::test(SpaceForm::class)
        ->set('name', 'Network')->set('visibility', $visibility)
        ->set('readTeamIds', $readTeams)->set('editTeamIds', [$docs->network->id])
        ->call('save')->assertHasNoErrors();

    return Space::query()->where('name', 'Network')->sole();
}

function writePage(Space $space, string $title, string $body, ?Page $parent = null): Page
{
    Livewire::withQueryParams($parent !== null ? ['parent' => $parent->id] : [])->test(PageEditor::class, ['space' => $space])
        ->set('title', $title)->set('body', $body)->call('save')->assertHasNoErrors();

    return Page::query()->where('title', $title)->sole();
}

it('lets team leads create spaces whose readers and editors follow its settings', function (): void {
    $space = networkSpace($this->docs);
    expect($space->owners()->pluck('users.id')->all())->toBe([$this->docs->lead->id])
        ->and($space->teamAccess())->toBe([$this->docs->network->id => 'edit'])
        ->and(AuditLog::query()->where('auditable_id', $space->id)->count())->toBeGreaterThanOrEqual(2);

    Livewire::test(SpaceForm::class)->set('name', 'network')->call('save')->assertHasErrors('name');

    // A member of the editing team writes even without an IT role.
    $this->actingAs($this->docs->engineer);
    $page = writePage($space, 'VPN', "# VPN\nConnect with the client.");
    expect($page->version)->toBe(1)->and(PageVersion::query()->where('page_id', $page->id)->count())->toBe(1);

    // IT staff read the space, other employees do not, nor see the menu entry.
    $this->actingAs($this->docs->agent);
    $this->get(route('documentation.pages.show', $page))->assertOk()->assertSee('Connect with the client.')->assertDontSee('Edit page');
    Livewire::test(PageEditor::class, ['page' => $page])->assertForbidden();
    $this->actingAs($this->docs->employee);
    $this->get(route('documentation.pages.show', $page))->assertForbidden();
    $this->get(route('documentation.spaces.show', $space))->assertForbidden();
    expect(collect(app(Navigation::class)->for($this->docs->employee))->pluck('label')->all())->not->toContain('Documentation');
    $this->get(route('documentation.spaces.create'))->assertForbidden();

    // Opened to everyone: employees read it, and the menu shows.
    $space->forceFill(['visibility' => 'everyone'])->save();
    $this->get(route('documentation.pages.show', $page))->assertOk();
    expect(collect(app(Navigation::class)->for($this->docs->employee))->pluck('label')->all())->toContain('Documentation');
});

it('keeps spaces of chosen teams to their members', function (): void {
    $space = networkSpace($this->docs, 'teams');
    $this->actingAs($this->docs->engineer);
    $page = writePage($space, 'Core switches', 'Stack of two.');

    $this->actingAs($this->docs->agent);
    $this->get(route('documentation.pages.show', $page))->assertForbidden();
    $this->actingAs($this->docs->lead);
    $this->get(route('documentation.pages.show', $page))->assertOk();
});

it('versions every save, refuses to overwrite a newer one, and goes back to an old one', function (): void {
    $space = networkSpace($this->docs);
    $this->actingAs($this->docs->engineer);
    $page = writePage($space, 'Backup', "Run nightly\nKeep 7 days");

    $first = Livewire::test(PageEditor::class, ['page' => $page]);
    Livewire::test(PageEditor::class, ['page' => $page])->set('body', "Run nightly at 02:00\nKeep 7 days")->set('summary', 'Time added')->call('save')->assertHasNoErrors();
    $first->set('body', 'My stale edit')->call('save')->assertHasErrors('body');
    expect($page->fresh()?->version)->toBe(2)->and($page->fresh()?->body)->toBe("Run nightly at 02:00\nKeep 7 days");

    Livewire::test(PageHistory::class, ['page' => $page->fresh()])
        ->assertSee('Version 2 compared with version 1')->assertSee('+ Run nightly at 02:00')->assertSee('- Run nightly')->assertSee('Time added')
        ->call('restore', 1)->assertRedirect(route('documentation.pages.show', $page));
    expect($page->fresh()?->version)->toBe(3)->and($page->fresh()?->body)->toBe("Run nightly\nKeep 7 days");

    // Saving without a change makes no version.
    Livewire::test(PageEditor::class, ['page' => $page->fresh()])->call('save');
    expect(PageVersion::query()->where('page_id', $page->id)->count())->toBe(3);
});

it('keeps the page tree whole: no loops, sub-pages move up when a page is deleted', function (): void {
    $space = networkSpace($this->docs);
    $this->actingAs($this->docs->engineer);
    $parent = writePage($space, 'Firewalls', 'Rules');
    $child = writePage($space, 'Rule review', 'Monthly', $parent);
    expect($child->parent_id)->toBe($parent->id);

    Livewire::test(PageEditor::class, ['page' => $parent])->assertDontSeeHtml('value="'.$child->id.'"')
        ->set('parentId', $child->id)->call('save')->assertHasErrors('parentId');

    Livewire::test(ShowPage::class, ['page' => $parent])->assertSee('Rule review')->call('delete')->assertRedirect(route('documentation.spaces.show', $space));
    expect($child->fresh()?->parent_id)->toBeNull()->and(Page::withTrashed()->find($parent->id)?->trashed())->toBeTrue();
});

it('renders Markdown without letting scripts through', function (): void {
    $space = networkSpace($this->docs);
    $this->actingAs($this->docs->engineer);
    $page = writePage($space, 'Unsafe', "**bold**\n\n<script>alert(1)</script>\n\n[click](javascript:alert(2))\n\n| A | B |\n| --- | --- |\n| 1 | 2 |");

    $this->get(route('documentation.pages.show', $page))->assertOk()
        ->assertSee('<strong>bold</strong>', false)->assertSee('<table>', false)
        ->assertDontSee('<script>alert(1)', false)->assertDontSee('javascript:alert', false);
});

it('finds pages by their words, only in spaces the person reads', function (): void {
    $space = networkSpace($this->docs);
    $this->actingAs($this->docs->engineer);
    writePage($space, 'VPN troubleshooting', 'If the tunnel drops, **restart** the client and check the certificate.');
    $this->actingAs($this->docs->lead);
    Livewire::test(SpaceForm::class)->set('name', 'Guides')->set('visibility', 'everyone')->call('save')->assertHasNoErrors();
    writePage(Space::query()->where('name', 'Guides')->sole(), 'Printing', 'Choose the printer near you; the certificate is not needed.');

    $this->actingAs($this->docs->agent);
    Livewire::test(DocsIndex::class)->set('q', 'certificate')->assertSee('2 pages found')->assertDontSee('[2,*]')->assertSeeHtml('<mark>certificate</mark>')->assertSee('restart the client')->assertDontSee('**restart**');
    $this->actingAs($this->docs->employee);
    Livewire::test(DocsIndex::class)->set('q', 'certificate')->assertSee('One page found')->assertSee('Printing')->assertDontSee('VPN troubleshooting');
});

it('links pages to records the editor may open, and keeps files for readers only', function (): void {
    $space = networkSpace($this->docs);
    $contract = Contract::factory()->create(['title' => 'Fibre link contract']);
    app(OrganizationContext::class)->set(Organization::factory()->create());
    $foreign = Contract::factory()->create();
    app(OrganizationContext::class)->set($this->docs->organization);

    $this->actingAs($this->docs->lead);
    $page = writePage($space, 'WAN', 'Two links.');
    Livewire::test(ShowPage::class, ['page' => $page])
        ->set('linkType', 'core.contract')->set('linkSearch', 'fibre')->assertSee('Fibre link contract')
        ->call('link', $contract->id)->assertHasNoErrors()
        ->call('link', $foreign->id)->assertHasErrors('linkSearch')
        ->set('files', [UploadedFile::fake()->createWithContent('topology.pdf', "%PDF-1.4\n%t\n")])->call('uploadFiles')->assertHasNoErrors()
        ->assertSee('topology.pdf');
    $attachment = $page->attachments()->sole();

    $this->actingAs($this->docs->agent);
    $this->get(route('core.attachments.download', $attachment))->assertOk();
    // The agent may not open contracts: neither the link nor the title it was given shows.
    Livewire::test(ShowPage::class, ['page' => $page])->assertDontSee('Fibre link contract')->assertSee('A record you cannot open')->assertDontSeeHtml(route('core.contracts.show', $contract));
    $this->actingAs($this->docs->employee);
    $this->get(route('core.attachments.download', $attachment))->assertForbidden();
});
