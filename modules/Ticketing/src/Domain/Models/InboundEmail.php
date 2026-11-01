<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Ticketing\Application\Policies\MailboxPolicy;
use Modules\Ticketing\Database\Factories\InboundEmailFactory;
use Modules\Ticketing\Domain\Email\InboundOutcome;

/**
 * One message read from a mailbox and what became of it. Written only by the
 * mail reader, pruned after the retention period (it holds email addresses).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $mailbox_id
 * @property string $message_key
 * @property string $from_address
 * @property ?string $subject
 * @property InboundOutcome $outcome
 * @property ?string $detail
 * @property ?string $ticket_id
 * @property ?Carbon $received_at
 * @property Carbon $processed_at
 * @property-read Mailbox $mailbox
 * @property-read ?Ticket $ticket
 */
#[Table('ticketing_inbound_emails')]
#[UseFactory(InboundEmailFactory::class)]
#[UsePolicy(MailboxPolicy::class)]
final class InboundEmail extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<InboundEmailFactory> */
    use HasFactory;

    use HasUuids;

    public $timestamps = false;

    /**
     * @return BelongsTo<Mailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    protected function casts(): array
    {
        return [
            'outcome' => InboundOutcome::class,
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
