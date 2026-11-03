<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Email;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Collaboration\StoreAttachment;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Actions\AddTicketAttachment;
use Modules\Ticketing\Application\Actions\AddTicketComment;
use Modules\Ticketing\Application\Actions\CreateTicket;
use Modules\Ticketing\Application\Actions\NewTicketData;
use Modules\Ticketing\Domain\Email\InboundOutcome;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Models\InboundEmail;
use Modules\Ticketing\Domain\Models\Mailbox;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Turns one message into a ticket or a reply on one, or ignores it. Runs in the
 * mailbox's organization. Only people with an account in that organization reach
 * a ticket (owner's decision: unknown senders are ignored, never answered, so
 * spammers get no confirmation). Automatic mail is ignored to prevent loops.
 *
 * The From address is only as good as the mail server's checks: a message
 * from IT staff that the server did not verify (SPF or DMARC pass) becomes an
 * internal note, so nobody can comment publicly in an agent's name by
 * forging their address. A mailbox may require verification of everyone.
 */
final readonly class ProcessInboundEmail
{
    private const string NUMBER_PATTERN = '/\[((?:INC|REQ|PRB|CHG)-\d{1,10})\]/';

    public function __construct(
        private Config $config,
        private ConnectionInterface $db,
        private Gate $gate,
        private CreateTicket $createTicket,
        private AddTicketComment $addComment,
        private AddTicketAttachment $addAttachment,
        private StoreAttachment $attachments,
    ) {}

    public function handle(Mailbox $mailbox, InboundMessage $message): InboundEmail
    {
        $key = $message->key();
        $previous = InboundEmail::query()
            ->where('mailbox_id', $mailbox->id)
            ->where('message_key', $key)
            ->where('outcome', '!=', InboundOutcome::Failed)
            ->first();

        if ($previous !== null) {
            return $previous;
        }

        $sender = $this->sender($mailbox, $message);
        $verified = $this->verifiedSender($message);

        // All or nothing: a failure halfway (e.g. storing an attachment) leaves no
        // half-made ticket, and ticket events are only dispatched after commit.
        return $this->db->transaction(function () use ($mailbox, $message, $sender, $verified): InboundEmail {
            [$outcome, $ticket, $detail] = match (true) {
                $message->size > $this->maxBytes() => [InboundOutcome::IgnoredTooLarge, null, null],
                $this->isAutomatic($mailbox, $message) => [InboundOutcome::IgnoredAutomatic, null, null],
                $this->failedAuthentication($message), $mailbox->require_verified_senders && ! $verified => [InboundOutcome::IgnoredSpoofed, null, null],
                $sender === null => [InboundOutcome::IgnoredUnknownSender, null, null],
                $this->overRateLimit($mailbox, $message) => [InboundOutcome::IgnoredRateLimited, null, null],
                default => $this->deliver($mailbox, $message, $sender, $verified),
            };

            return $this->log($mailbox, $message, $outcome, $ticket, $detail);
        });
    }

    public function logFailure(Mailbox $mailbox, InboundMessage $message, string $detail): InboundEmail
    {
        return $this->log($mailbox, $message, InboundOutcome::Failed, null, $detail);
    }

    /**
     * @return array{InboundOutcome, Ticket, ?string}
     */
    private function deliver(Mailbox $mailbox, InboundMessage $message, User $sender, bool $verified): array
    {
        [$files, $refused] = $this->acceptedAttachments($message);
        $body = EmailBody::newPart($message->text);

        if ($refused !== []) {
            $body = trim($body."\n\n".__('ticketing::email.attachments_refused', ['files' => implode(', ', $refused)]));
        }

        $referenced = $this->referencedTicket($message);

        if ($referenced !== null && $this->gate->forUser($sender)->allows('comment', $referenced)) {
            $body = $body !== '' ? $body : __('ticketing::email.attachments_only');
            // Staff write to requesters: unverified, it stays among staff.
            $unverifiedStaff = ! $verified && $this->gate->forUser($sender)->allows('work', $referenced);

            if ($unverifiedStaff) {
                $body = __('ticketing::email.unverified_staff', ['email' => $sender->email])."\n\n".$body;
            }

            $this->addComment->handle($referenced, $sender, $body, $unverifiedStaff);
            $this->attach($referenced, $sender, $files, $unverifiedStaff);

            return [InboundOutcome::Commented, $referenced, $this->refusedDetail($refused)];
        }

        // A reply to a closed ticket, or to one the sender cannot see, starts a new one.
        if ($referenced !== null && $this->gate->forUser($sender)->allows('view', $referenced)) {
            $body = trim(__('ticketing::email.follow_up', ['number' => $referenced->number])."\n\n".$body);
        }

        if (! $verified && $sender->can('ticketing.ticket.work')) {
            $body = trim(__('ticketing::email.unverified_staff', ['email' => $sender->email])."\n\n".$body);
        }

        $queue = $mailbox->queue;
        $ticket = $this->createTicket->handle(new NewTicketData(
            type: $mailbox->ticket_type,
            title: $this->title($message->subject),
            description: $body !== '' ? $body : __('ticketing::email.no_text'),
            channel: TicketChannel::Email,
            queueId: $queue !== null && $queue->is_active ? $queue->id : null,
        ), $sender, $sender);
        $this->attach($ticket, $sender, $files);

        return [InboundOutcome::Created, $ticket, $this->refusedDetail($refused)];
    }

    private function sender(Mailbox $mailbox, InboundMessage $message): ?User
    {
        $address = mb_strtolower(trim($message->fromAddress));

        if ($address === '') {
            return null;
        }

        $user = User::query()
            ->whereRaw('lower(email) = ?', [$address])
            ->where('is_active', true)
            ->whereHas('organizations', fn ($query) => $query->whereKey($mailbox->organization_id))
            ->first();

        return $user !== null && $user->canSignIn() ? $user : null;
    }

    /**
     * Auto-replies, bounces, mailing lists and our own notifications (RFC 3834 and
     * the headers Exchange and common tools set).
     */
    private function isAutomatic(Mailbox $mailbox, InboundMessage $message): bool
    {
        $from = mb_strtolower(trim($message->fromAddress));
        $localPart = strstr($from, '@', true);
        $notificationsFrom = $this->config->get('mail.from.address');
        $ownAddresses = [$mailbox->address, ...(is_string($notificationsFrom) ? [mb_strtolower($notificationsFrom)] : [])];
        $autoSubmitted = mb_strtolower(trim((string) $message->header('auto-submitted')));
        $precedence = mb_strtolower(trim((string) $message->header('precedence')));
        $suppress = mb_strtolower((string) $message->header('x-auto-response-suppress'));

        return in_array($from, $ownAddresses, true)
            || in_array($localPart, ['mailer-daemon', 'postmaster'], true)
            || ($autoSubmitted !== '' && $autoSubmitted !== 'no')
            || in_array($precedence, ['bulk', 'junk', 'list', 'auto_reply'], true)
            || preg_match('/\b(all|oof|autoreply)\b/', $suppress) === 1
            || $message->hasHeader('x-autoreply')
            || $message->hasHeader('x-autorespond')
            || $message->hasHeader('list-id')
            || $message->hasHeader('list-unsubscribe')
            || trim((string) $message->header('return-path')) === '<>'
            || str_starts_with(mb_strtolower((string) $message->header('content-type')), 'multipart/report');
    }

    /**
     * When the receiving server checked the sender (Authentication-Results), a
     * failed SPF or DMARC check means the From address cannot be trusted.
     */
    private function failedAuthentication(InboundMessage $message): bool
    {
        foreach ($this->authenticationResults($message) as $result) {
            if (preg_match('/\b(spf|dmarc)=fail\b/i', $result) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the receiving server confirmed the sender: a DMARC pass, or an
     * SPF pass (the envelope sender's domain; most servers then also align it).
     */
    private function verifiedSender(InboundMessage $message): bool
    {
        foreach ($this->authenticationResults($message) as $result) {
            if (preg_match('/\b(spf|dmarc)=pass\b/i', $result) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The Authentication-Results headers written by the servers named in
     * ticketing.email.trusted_authserv_ids (all of them when none is named).
     *
     * @return list<string>
     */
    private function authenticationResults(InboundMessage $message): array
    {
        $trusted = $this->config->get('ticketing.email.trusted_authserv_ids');
        $trusted = is_array($trusted) ? array_map(fn (mixed $id): string => mb_strtolower(is_string($id) ? $id : ''), $trusted) : [];
        $results = [];

        foreach ($message->headers['authentication-results'] ?? [] as $result) {
            // "mx.example.com 1; spf=pass ...": the authserv-id comes first, maybe with a version.
            $authservId = mb_strtolower((string) strtok(trim(strstr($result, ';', true) ?: $result), " \t"));

            if ($trusted === [] || in_array($authservId, $trusted, true)) {
                $results[] = $result;
            }
        }

        return $results;
    }

    private function overRateLimit(Mailbox $mailbox, InboundMessage $message): bool
    {
        $limit = $this->config->get('ticketing.email.max_per_sender_per_hour');

        if (! is_int($limit) || $limit <= 0) {
            return false;
        }

        return InboundEmail::query()
            ->where('mailbox_id', $mailbox->id)
            ->where('from_address', mb_strtolower(trim($message->fromAddress)))
            ->whereIn('outcome', [InboundOutcome::Created, InboundOutcome::Commented])
            ->where('processed_at', '>=', now()->subHour())
            ->count() >= $limit;
    }

    private function referencedTicket(InboundMessage $message): ?Ticket
    {
        if (preg_match(self::NUMBER_PATTERN, $message->subject, $matches) !== 1) {
            return null;
        }

        // Scoped to the mailbox's organization: numbers repeat across organizations.
        return Ticket::query()->where('number', $matches[1])->first();
    }

    private function title(string $subject): string
    {
        $title = (string) preg_replace(['/^\s*((re|fw|fwd|aw|wg|rv|tr|sv)\s*:\s*)+/i', self::NUMBER_PATTERN], '', $subject);
        $title = trim((string) preg_replace('/\s+/', ' ', $title));

        return mb_substr($title !== '' ? $title : __('ticketing::email.no_subject'), 0, 200);
    }

    /**
     * @return array{list<UploadedFile>, list<string>}
     */
    private function acceptedAttachments(InboundMessage $message): array
    {
        $accepted = [];
        $refused = [];

        foreach ($message->attachments as $attachment) {
            // Signature logos and other inline images are not attachments people meant to send.
            if ($attachment->inline) {
                continue;
            }

            $name = StoreAttachment::cleanName($attachment->filename);
            $path = tempnam(sys_get_temp_dir(), 'mail');

            if ($path === false || file_put_contents($path, $attachment->contents) === false) {
                $refused[] = $name;

                continue;
            }

            $file = new UploadedFile($path, $name, null, null, true);

            try {
                $this->attachments->assertAcceptable($file);
                $accepted[] = $file;
            } catch (ValidationException) {
                @unlink($path);
                $refused[] = $name;
            }
        }

        return [$accepted, $refused];
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function attach(Ticket $ticket, User $sender, array $files, bool $internal = false): void
    {
        foreach ($files as $file) {
            try {
                $this->addAttachment->handle($ticket, $sender, $file, $internal);
            } finally {
                @unlink($file->getRealPath() ?: $file->getPathname());
            }
        }
    }

    /**
     * @param  list<string>  $refused
     */
    private function refusedDetail(array $refused): ?string
    {
        return $refused !== [] ? 'attachments_refused:'.count($refused) : null;
    }

    private function maxBytes(): int
    {
        $kilobytes = $this->config->get('ticketing.email.max_message_kilobytes');

        return (is_int($kilobytes) && $kilobytes > 0 ? $kilobytes : 25_600) * 1024;
    }

    private function log(Mailbox $mailbox, InboundMessage $message, InboundOutcome $outcome, ?Ticket $ticket, ?string $detail): InboundEmail
    {
        $entry = new InboundEmail;
        $entry->forceFill([
            'organization_id' => $mailbox->organization_id,
            'mailbox_id' => $mailbox->id,
            'message_key' => $message->key(),
            'from_address' => mb_substr(mb_strtolower(trim($message->fromAddress)), 0, 320),
            'subject' => mb_substr($message->subject, 0, 255),
            'outcome' => $outcome,
            'detail' => $detail !== null ? mb_substr($detail, 0, 255) : null,
            'ticket_id' => $ticket?->id,
            'received_at' => $message->receivedAt,
            'processed_at' => now(),
        ])->save();

        return $entry;
    }
}
