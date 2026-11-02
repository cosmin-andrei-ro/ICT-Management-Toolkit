<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Livewire\Livewire;
use Modules\Core\Domain\Links\KnowledgeArticles;
use Modules\Documentation\Application\Actions\ManagePageLinks;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\Space;
use Modules\Documentation\Tests\Support\DocsScenario;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Http\Livewire\Desk\ShowTicket as DeskShowTicket;
use Modules\Ticketing\Http\Livewire\Portal\CreateTicket;
use Modules\Ticketing\Http\Livewire\Portal\ShowTicket as PortalShowTicket;

beforeEach(function (): void {
    $this->docs = DocsScenario::create();
    $this->guides = Space::factory()->create(['name' => 'Guides', 'visibility' => 'everyone']);
    $this->internal = Space::factory()->create(['name' => 'Network', 'visibility' => 'staff']);
});

function publishedPage(Space $space, string $title, string $body, array $attributes = []): Page
{
    return Page::factory()->create(['space_id' => $space->id, 'title' => $title, 'body' => $body, 'published_title' => $title, 'published_body' => $body, 'published_version' => 1, 'published_at' => now(), ...$attributes]);
}

it('suggests published articles matching any word of a text, among those the person reads', function (): void {
    $printer = publishedPage($this->guides, 'Printer offline', 'Turn the printer off and on, then check the cable.');
    publishedPage($this->internal, 'Printer server', 'The print server runs on PRT01.');
    // A draft only: never suggested.
    Page::factory()->create(['space_id' => $this->guides->id, 'title' => 'Printer secret draft', 'body' => 'printer']);

    $articles = app(KnowledgeArticles::class);
    expect(array_map(fn ($article) => $article->title, $articles->suggest('My printer says offline!', $this->docs->employee)))->toBe(['Printer offline'])
        ->and(array_map(fn ($article) => $article->title, $articles->suggest('printer', $this->docs->agent)))->toEqualCanonicalizing(['Printer offline', 'Printer server'])
        ->and($articles->suggest('a & | ! :* (', $this->docs->agent))->toBe([]);
    // The beginning of a word is enough.
    expect($articles->suggest('print problem', $this->docs->employee)[0]->url)->toBe(route('documentation.pages.show', $printer));
});

it('suggests articles while a request is written in the portal, and shows those linked to the ticket', function (): void {
    $vpn = publishedPage($this->guides, 'VPN does not connect', 'Restart the VPN client and check your password.');
    $internal = publishedPage($this->internal, 'VPN gateway restart', 'Steps for IT.');

    $this->actingAs($this->docs->employee);
    Livewire::test(CreateTicket::class)->set('title', 'VPN broken')->assertSee('These articles may solve it right away')->assertSee('VPN does not connect')->assertDontSee('VPN gateway restart');

    $ticket = Ticket::factory()->create(['requester_id' => $this->docs->employee->id, 'title' => 'VPN broken since this morning']);
    $this->actingAs($this->docs->lead);
    app(ManagePageLinks::class)->link($vpn, 'ticketing.ticket', $ticket->id, $this->docs->lead);
    app(ManagePageLinks::class)->link($internal, 'ticketing.ticket', $ticket->id, $this->docs->lead);

    // The requester sees only the articles they may read.
    $this->actingAs($this->docs->employee);
    Livewire::test(PortalShowTicket::class, ['ticket' => $ticket])->assertSee('Articles about your request')->assertSee('VPN does not connect')->assertDontSee('VPN gateway restart');

    // Agents see both, and other suggestions.
    publishedPage($this->internal, 'VPN certificates', 'Renew yearly.');
    $this->actingAs($this->docs->agent);
    Livewire::test(DeskShowTicket::class, ['ticket' => $ticket])->assertSee('VPN gateway restart')->assertSee('May help:')->assertSee('VPN certificates');
});

it('lets other modules link documentation pages as records', function (): void {
    publishedPage($this->internal, 'Backup procedure', 'Nightly.');
    $records = app(Modules\Core\Domain\Links\LinkedRecords::class);

    expect(array_map(fn ($record) => $record->title, $records->search('documentation.page', 'backup', $this->docs->agent)))->toBe(['Backup procedure'])
        ->and($records->search('documentation.page', 'backup', $this->docs->employee))->toBe([]);
});
