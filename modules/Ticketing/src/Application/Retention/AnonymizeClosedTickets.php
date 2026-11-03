<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Retention;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Illuminate\Database\ConnectionInterface;
use Modules\Core\Application\Audit\AuditRedaction;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\Comment;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\InboundEmail;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * The retention policy of tickets (ADR 0060): a ticket closed before the
 * cut-off keeps what statistics and traceability need - number, type,
 * category, service, queue, assignee, dates, SLA, linked CMDB items - and
 * loses the requester, the title and description, the resolution note, the
 * custom fields, the watchers, the comments and the files, here and in the
 * audit log.
 */
final readonly class AnonymizeClosedTickets
{
    /** Fields whose values leave the ticket's audit entries. */
    private const array REDACTED = ['title', 'description', 'resolution_note', 'custom_fields', 'requester_id'];

    public function __construct(
        private ConnectionInterface $db,
        private Filesystem $filesystem,
        private AuditRedaction $redaction,
    ) {}

    /**
     * @return int Tickets anonymized.
     */
    public function __invoke(string $organizationId, CarbonImmutable $before): int
    {
        $count = 0;
        $tickets = Ticket::query()->withTrashed()
            ->where('status', TicketStatus::Closed->value)->where('closed_at', '<', $before)->whereNull('anonymized_at')
            ->orderBy('closed_at');

        foreach ($tickets->lazyById(100) as $ticket) {
            $this->db->transaction(fn () => $this->anonymize($ticket));
            $count++;
        }

        return $count;
    }

    private function anonymize(Ticket $ticket): void
    {
        foreach (Attachment::query()->withTrashed()->where('attachable_type', $ticket->getMorphClass())->where('attachable_id', $ticket->id)->get() as $attachment) {
            $this->filesystem->disk($attachment->disk)->delete($attachment->path);
            $this->redaction->removeFields($attachment, ['original_name']);
            // Query-level: a model delete would log the old values, the very text being removed.
            Attachment::query()->withTrashed()->whereKey($attachment->id)->forceDelete();
        }

        foreach (Comment::query()->withTrashed()->where('commentable_type', $ticket->getMorphClass())->where('commentable_id', $ticket->id)->get() as $comment) {
            $this->redaction->removeFields($comment, ['body']);
            Comment::query()->withTrashed()->whereKey($comment->id)->forceDelete();
        }

        $ticket->watchers()->detach();
        InboundEmail::query()->where('ticket_id', $ticket->id)->update(['from_address' => 'anonymized', 'subject' => null]);

        // A plain query: the old text must not reach the audit log as an "updated" entry.
        $this->db->table('ticketing_tickets')->where('id', $ticket->id)->update([
            'title' => __('ticketing::retention.title'),
            'description' => '',
            'resolution_note' => null,
            'custom_fields' => '{}',
            'requester_id' => null,
            'anonymized_at' => now(),
        ]);

        $this->redaction->removeFields($ticket, self::REDACTED);
    }
}
