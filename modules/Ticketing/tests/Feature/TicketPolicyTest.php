<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Ticketing\Application\Actions\AssignTicket;
use Modules\Ticketing\Application\Actions\ResolveTicket;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    $this->desk = TicketingScenario::create();
    $this->ticket = $this->desk->ticket();
    $this->colleague = $this->desk->member(DefaultRole::Requester);
});

function allows(User $user, string $ability, mixed $argument): bool
{
    return Gate::forUser($user)->allows($ability, $argument);
}

it('shows a requester their own tickets and those they watch, nothing else', function (): void {
    $othersTicket = $this->desk->ticket($this->colleague);

    expect(allows($this->desk->requester, 'view', $this->ticket))->toBeTrue()
        ->and(allows($this->desk->requester, 'view', $othersTicket))->toBeFalse()
        ->and(Ticket::query()->visibleToRequester($this->desk->requester)->pluck('id')->all())->toBe([$this->ticket->id]);

    $othersTicket->watchers()->attach($this->desk->requester);

    expect(allows($this->desk->requester, 'view', $othersTicket))->toBeTrue()
        ->and(Ticket::query()->visibleToRequester($this->desk->requester)->count())->toBe(2);
});

it('never shows internal content to requesters or watchers', function (): void {
    $this->ticket->watchers()->attach($this->colleague);

    expect(allows($this->desk->requester, 'viewInternal', $this->ticket))->toBeFalse()
        ->and(allows($this->colleague, 'viewInternal', $this->ticket))->toBeFalse()
        ->and(allows($this->desk->requester, 'commentInternally', $this->ticket))->toBeFalse()
        ->and(allows($this->desk->agent, 'viewInternal', $this->ticket))->toBeTrue();
});

it('lets agents see and work every ticket of their organization', function (): void {
    $othersTicket = $this->desk->ticket($this->colleague);

    expect(allows($this->desk->agent, 'view', $othersTicket))->toBeTrue()
        ->and(allows($this->desk->agent, 'work', $othersTicket))->toBeTrue()
        ->and(allows($this->desk->requester, 'work', $this->ticket))->toBeFalse()
        ->and(allows($this->desk->requester, 'assign', $this->ticket))->toBeFalse();
});

it('denies agents of another organization', function (): void {
    $otherOrganization = Organization::factory()->create();
    $foreignAgent = $this->desk->member(DefaultRole::Agent, $otherOrganization);
    app(OrganizationContext::class)->set($otherOrganization);
    $loadedAnyway = Ticket::query()->withoutGlobalScope(OrganizationScope::class)->findOrFail($this->ticket->id);

    expect(Ticket::query()->find($this->ticket->id))->toBeNull()
        ->and(allows($foreignAgent, 'view', $loadedAnyway))->toBeFalse()
        ->and(allows($foreignAgent, 'work', $loadedAnyway))->toBeFalse();
});

it('lets the requester cancel only a ticket nobody has started on', function (): void {
    expect(allows($this->desk->requester, 'cancel', $this->ticket))->toBeTrue();

    app(AssignTicket::class)->handle($this->ticket, $this->desk->agent, null, $this->desk->lead);

    expect(allows($this->desk->requester, 'cancel', $this->ticket->fresh()))->toBeFalse()
        ->and(allows($this->desk->agent, 'cancel', $this->ticket->fresh()))->toBeTrue();
});

it('lets the requester reopen or close only a resolved ticket', function (): void {
    expect(allows($this->desk->requester, 'reopen', $this->ticket))->toBeFalse();

    app(ResolveTicket::class)->handle($this->ticket, ResolutionCode::Fixed, '', $this->desk->agent);

    expect(allows($this->desk->requester, 'reopen', $this->ticket->fresh()))->toBeTrue()
        ->and(allows($this->desk->requester, 'close', $this->ticket->fresh()))->toBeTrue()
        ->and(allows($this->colleague, 'reopen', $this->ticket->fresh()))->toBeFalse();
});

it('reserves ticketing settings to team leads and administrators', function (): void {
    expect(allows($this->desk->lead, 'create', Modules\Ticketing\Domain\Models\Queue::class))->toBeTrue()
        ->and(allows($this->desk->agent, 'create', Modules\Ticketing\Domain\Models\Queue::class))->toBeFalse()
        ->and(allows($this->desk->requester, 'viewAny', Modules\Ticketing\Domain\Models\Category::class))->toBeFalse();
});
