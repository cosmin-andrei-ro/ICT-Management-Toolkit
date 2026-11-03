<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Ticketing\Application\Policies\MailboxPolicy;
use Modules\Ticketing\Database\Factories\MailboxFactory;
use Modules\Ticketing\Domain\Email\MailEncryption;
use Modules\Ticketing\Domain\Enums\TicketType;

/**
 * An IMAP mailbox turned into tickets. The password is encrypted at rest, hidden
 * from serialization and the audit trail; credentials_updated_at records changes.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $address
 * @property string $host
 * @property int $port
 * @property MailEncryption $encryption
 * @property string $username
 * @property string $password
 * @property ?Carbon $credentials_updated_at
 * @property string $folder
 * @property ?string $processed_folder
 * @property ?string $queue_id
 * @property TicketType $ticket_type
 * @property bool $is_active
 * @property bool $require_verified_senders
 * @property ?Carbon $last_checked_at
 * @property ?string $last_error
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?Queue $queue
 */
#[Table('ticketing_mailboxes')]
#[UseFactory(MailboxFactory::class)]
#[UsePolicy(MailboxPolicy::class)]
#[Hidden(['password'])]
#[Fillable(['name', 'address', 'host', 'port', 'encryption', 'username', 'password', 'folder', 'processed_folder', 'queue_id', 'ticket_type', 'is_active', 'require_verified_senders'])]
final class Mailbox extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<MailboxFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /**
     * Mirror the column defaults, so new instances know them before saving.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'folder' => 'INBOX',
        'ticket_type' => 'incident',
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        self::saving(function (self $mailbox): void {
            $mailbox->address = mb_strtolower(trim($mailbox->address));

            if ($mailbox->isDirty(['host', 'port', 'username', 'password'])) {
                $mailbox->credentials_updated_at = now();
            }
        });
    }

    /**
     * @return BelongsTo<Queue, $this>
     */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    /**
     * @return list<string>
     */
    public function auditExclude(): array
    {
        return ['last_checked_at', 'last_error'];
    }

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'encryption' => MailEncryption::class,
            'password' => 'encrypted',
            'credentials_updated_at' => 'datetime',
            'ticket_type' => TicketType::class,
            'is_active' => 'boolean',
            'require_verified_senders' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }
}
