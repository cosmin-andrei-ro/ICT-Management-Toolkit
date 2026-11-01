<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Actions\CreateTicket;
use Modules\Ticketing\Application\Actions\NewTicketData;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Http\Requests\Api\StoreTicketRequest;
use Modules\Ticketing\Http\Resources\TicketResource;

final readonly class TicketController
{
    public function __construct(private Gate $gate) {}

    /**
     * List tickets visible to the token's user: all tickets of the organization for
     * service desk staff, otherwise the user's own and watched tickets.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Ticket::class);

        $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'closed', ...array_map(fn (TicketStatus $s): string => $s->value, TicketStatus::cases())])],
            'search' => ['sometimes', 'string', 'max:200'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $this->user($request);
        $status = $request->string('status')->toString();

        $tickets = Ticket::query()
            ->unless($user->can('ticketing.ticket.view_all'), fn ($query) => $query->visibleToRequester($user))
            ->search($request->string('search')->toString())
            ->when($status === 'open', fn ($query) => $query->whereIn('status', TicketStatus::open()))
            ->when($status === 'closed', fn ($query) => $query->whereNotIn('status', TicketStatus::open()))
            ->when(TicketStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
            ->with(['requester', 'assignee', 'queue', 'category', 'service'])
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return TicketResource::collection($tickets);
    }

    /**
     * Open a ticket for the token's user.
     */
    public function store(StoreTicketRequest $request, CreateTicket $createTicket): JsonResponse
    {
        $user = $this->user($request);
        $categoryId = $request->string('category_id')->toString();

        $ticket = $createTicket->handle(new NewTicketData(
            type: $request->enum('type', TicketType::class) ?? TicketType::Incident,
            title: $request->string('title')->toString(),
            description: $request->string('description')->toString(),
            impact: $request->enum('impact', Impact::class) ?? Impact::Low,
            urgency: $request->enum('urgency', Urgency::class) ?? Urgency::Low,
            channel: TicketChannel::Api,
            categoryId: $categoryId !== '' ? $categoryId : null,
        ), $user, $user);

        return (new TicketResource($ticket->load(['requester', 'assignee', 'queue', 'category'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * A ticket with its conversation; internal notes only for service desk staff.
     */
    public function show(Request $request, Ticket $ticket): TicketResource
    {
        $this->gate->authorize('view', $ticket);

        $showInternal = $this->user($request)->can('viewInternal', $ticket);

        $ticket->load(['requester', 'assignee', 'queue', 'category', 'service', 'comments' => fn ($query) => $query
            ->when(! $showInternal, fn ($inner) => $inner->where('is_internal', false))
            ->with('author')]);

        return new TicketResource($ticket);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
