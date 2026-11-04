<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Api\ApiQuery;
use Modules\Ticketing\Application\Actions\CreateTicket;
use Modules\Ticketing\Application\Actions\NewTicketData;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Priority;
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
     * IT support staff, otherwise the user's own and watched tickets.
     *
     * Filters: `filter[status]` (open, closed or a status), `filter[type]`, `filter[priority]`,
     * `filter[queue_id]`, `filter[assignee_id]`, `filter[search]`. Sorts: `created_at`,
     * `updated_at`, `number`, `priority`; newest first by default. The `status` and
     * `search` parameters of the first version still work.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Ticket::class);

        $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'closed', ...array_map(fn (TicketStatus $s): string => $s->value, TicketStatus::cases())])],
            'search' => ['sometimes', 'string', 'max:200'],
        ]);

        // The first version's parameters, as filters.
        foreach (['status', 'search'] as $legacy) {
            if ($request->has($legacy) && ! $request->has("filter.{$legacy}")) {
                $request->query->set('filter', [...(array) $request->query('filter', []), $legacy => $request->string($legacy)->toString()]);
            }
        }

        $user = $this->user($request);
        $invalid = fn (string $value): ValidationException => ValidationException::withMessages(['filter' => __('ticketing::api.invalid_filter', ['value' => $value])]);

        $tickets = ApiQuery::paginate($request, Ticket::query()
            ->unless($user->can('ticketing.ticket.view_all'), fn ($query) => $query->visibleToRequester($user))
            ->with(['requester', 'assignee', 'queue', 'category', 'service']), [
                'status' => fn (Builder $query, string $value) => match (true) {
                    $value === 'open' => $query->whereIn('status', TicketStatus::open()),
                    $value === 'closed' => $query->whereNotIn('status', TicketStatus::open()),
                    TicketStatus::tryFrom($value) !== null => $query->where('status', $value),
                    default => throw $invalid($value),
                },
                'type' => fn (Builder $query, string $value) => $query->where('type', TicketType::tryFrom($value)->value ?? throw $invalid($value)),
                'priority' => fn (Builder $query, string $value) => $query->where('priority', Priority::tryFrom($value)->value ?? throw $invalid($value)),
                'queue_id' => fn (Builder $query, string $value) => $query->where('queue_id', Str::isUuid($value) ? $value : null),
                'assignee_id' => fn (Builder $query, string $value) => $query->where('assignee_id', Str::isUuid($value) ? $value : null),
                'search' => fn (Builder $query, string $value) => $query->search($value),
            ], ['created_at' => 'created_at', 'updated_at' => 'updated_at', 'number' => 'number', 'priority' => 'priority'], '-created_at');

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
     * A ticket with its conversation; internal notes only for IT support staff.
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
