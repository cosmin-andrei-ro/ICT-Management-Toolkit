<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Actions\AddTicketComment;
use Modules\Ticketing\Application\Actions\TicketNotOpen;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Http\Requests\Api\StoreTicketCommentRequest;
use Modules\Ticketing\Http\Resources\CommentResource;

final readonly class TicketCommentController
{
    /**
     * Add a comment. Internal notes require service desk permissions.
     */
    public function store(StoreTicketCommentRequest $request, Ticket $ticket, AddTicketComment $addComment): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        try {
            $comment = $addComment->handle($ticket, $user, $request->string('body')->toString(), $request->boolean('internal'));
        } catch (TicketNotOpen) {
            abort(409, __('ticketing::ui.action_failed'));
        }

        return (new CommentResource($comment->load('author')))->response()->setStatusCode(201);
    }
}
