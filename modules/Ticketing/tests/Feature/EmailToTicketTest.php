<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\Comment;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Actions\CancelTicket;
use Modules\Ticketing\Application\Email\EmailBody;
use Modules\Ticketing\Application\Email\FetchMailbox;
use Modules\Ticketing\Application\Email\InboundAttachment;
use Modules\Ticketing\Application\Email\InboundMessage;
use Modules\Ticketing\Application\Email\MailboxReader;
use Modules\Ticketing\Application\Email\ProcessInboundEmail;
use Modules\Ticketing\Application\Email\PruneInboundEmails;
use Modules\Ticketing\Application\Notifications\TicketUpdate;
use Modules\Ticketing\Domain\Email\InboundOutcome;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\InboundEmail;
use Modules\Ticketing\Domain\Models\Mailbox;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Tests\Support\FakeMailboxReader;
use Modules\Ticketing\Tests\Support\TicketingScenario;

const PNG_PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

beforeEach(function (): void {
    Notification::fake();
    $this->desk = TicketingScenario::create();
    $this->mailbox = Mailbox::factory()->create(['address' => 'helpdesk@example.com']);
});

/**
 * @param  array<string, list<string>>  $headers
 * @param  list<InboundAttachment>  $attachments
 */
function email(string $from, string $subject = 'Printer offline', string $text = 'The printer on floor 2 is offline.', array $headers = [], array $attachments = [], ?string $messageId = null): InboundMessage
{
    static $uid = 0;
    $uid++;

    return new InboundMessage($uid, $messageId ?? "<{$uid}.".uniqid().'@mail.example.com>', $from, $subject, $text, now()->toImmutable(), 2048, $headers, $attachments);
}

function intake(Mailbox $mailbox, InboundMessage $message): InboundEmail
{
    return app(ProcessInboundEmail::class)->handle($mailbox, $message);
}

describe('new tickets', function (): void {
    it('creates a ticket for a known sender, by email channel, in the mailbox queue', function (): void {
        $entry = intake($this->mailbox, email(strtoupper($this->desk->requester->email), 'RE: Printer offline'));

        $ticket = Ticket::query()->sole();
        expect($entry->outcome)->toBe(InboundOutcome::Created)
            ->and($entry->ticket_id)->toBe($ticket->id)
            ->and($ticket->requester_id)->toBe($this->desk->requester->id)
            ->and($ticket->channel)->toBe(TicketChannel::Email)
            ->and($ticket->title)->toBe('Printer offline')
            ->and($ticket->queue_id)->toBe($this->desk->queue->id)
            ->and($ticket->description)->toBe('The printer on floor 2 is offline.');

        Notification::assertSentTo($this->desk->requester, TicketUpdate::class, fn (TicketUpdate $n): bool => $n->kind === 'created' && $n->replyTo === 'helpdesk@example.com');
    });

    it('handles each message once', function (): void {
        $message = email($this->desk->requester->email, messageId: '<same@mail.example.com>');

        intake($this->mailbox, $message);
        intake($this->mailbox, $message);

        expect(Ticket::query()->count())->toBe(1)
            ->and(InboundEmail::query()->count())->toBe(1);
    });

    it('stores allowed attachments, skips inline images and names refused files', function (): void {
        intake($this->mailbox, email($this->desk->requester->email, attachments: [
            new InboundAttachment('screenshot.png', (string) base64_decode(PNG_PIXEL)),
            new InboundAttachment('logo.png', (string) base64_decode(PNG_PIXEL), inline: true),
            new InboundAttachment('../../evil.exe', "MZ\x90\x00".str_repeat("\x00", 200)),
        ]));

        $ticket = Ticket::query()->sole();
        expect(Attachment::query()->where('attachable_id', $ticket->id)->pluck('original_name')->all())->toBe(['screenshot.png'])
            ->and($ticket->description)->toContain('Attachments not accepted')->toContain('evil.exe')->not->toContain('../');
    });
});

describe('replies', function (): void {
    it('adds a reply to the ticket in the subject, without the quoted conversation', function (): void {
        $ticket = $this->desk->ticket();

        $entry = intake($this->mailbox, email($this->desk->requester->email, "RE: [{$ticket->number}] Reply from the service desk", "It works now, thanks.\n\n".EmailBody::MARKER."\nold text"));

        $comment = Comment::query()->where('commentable_id', $ticket->id)->sole();
        expect($entry->outcome)->toBe(InboundOutcome::Commented)
            ->and($comment->body)->toBe('It works now, thanks.')
            ->and($comment->is_internal)->toBeFalse()
            ->and(Ticket::query()->count())->toBe(1);
    });

    it('opens a new ticket when the sender may not write on the referenced one', function (): void {
        $someoneElses = $this->desk->ticket();
        $colleague = $this->desk->member(DefaultRole::Requester);

        intake($this->mailbox, email($colleague->email, "[{$someoneElses->number}] Also broken for me"));

        expect(Comment::query()->where('commentable_id', $someoneElses->id)->exists())->toBeFalse()
            ->and(Ticket::query()->where('requester_id', $colleague->id)->sole()->title)->toBe('Also broken for me');
    });

    it('opens a follow-up ticket when the referenced one is closed', function (): void {
        $ticket = $this->desk->ticket();
        app(CancelTicket::class)->handle($ticket, 'Duplicate', $this->desk->agent);

        intake($this->mailbox, email($this->desk->requester->email, "RE: [{$ticket->number}] Cancelled", 'It is still broken.'));

        $followUp = Ticket::query()->whereKeyNot($ticket->id)->sole();
        expect($followUp->description)->toStartWith("Follow-up of {$ticket->number}")
            ->and($ticket->fresh()->status)->toBe(TicketStatus::Cancelled);
    });

    it('never matches a ticket number of another organization', function (): void {
        $foreign = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Ticket::factory()->create(['number' => 'INC-424242']));

        intake($this->mailbox, email($this->desk->requester->email, '[INC-424242] Hello'));

        expect(Comment::query()->where('commentable_id', $foreign->id)->exists())->toBeFalse()
            ->and(Ticket::query()->where('requester_id', $this->desk->requester->id)->count())->toBe(1);
    });
});

describe('ignored messages', function (): void {
    it('ignores unknown senders and people of other organizations, without answering', function (): void {
        $outsider = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => User::factory()->create());
        $inactive = $this->desk->member(DefaultRole::Requester);
        $inactive->forceFill(['is_active' => false])->save();

        foreach (['stranger@spam.example', $outsider->email, $inactive->email] as $from) {
            expect(intake($this->mailbox, email($from))->outcome)->toBe(InboundOutcome::IgnoredUnknownSender);
        }

        expect(Ticket::query()->count())->toBe(0);
        Notification::assertNothingSent();
    });

    it('ignores automatic messages, to prevent mail loops', function (array $headers, ?string $from = null): void {
        $entry = intake($this->mailbox, email($from ?? $this->desk->requester->email, headers: $headers));

        expect($entry->outcome)->toBe(InboundOutcome::IgnoredAutomatic)
            ->and(Ticket::query()->count())->toBe(0);
    })->with([
        'out of office' => [['auto-submitted' => ['auto-replied']]],
        'Exchange suppression' => [['x-auto-response-suppress' => ['All']]],
        'bulk' => [['precedence' => ['bulk']]],
        'mailing list' => [['list-id' => ['<it.lists.example.com>']]],
        'bounce' => [['return-path' => ['<>']]],
        'delivery report' => [['content-type' => ['multipart/report; report-type=delivery-status']]],
        'our own mailbox' => [[], 'helpdesk@example.com'],
    ]);

    it('accepts messages that say they are not automatic', function (): void {
        expect(intake($this->mailbox, email($this->desk->requester->email, headers: ['auto-submitted' => ['no']]))->outcome)->toBe(InboundOutcome::Created);
    });

    it('ignores senders that failed SPF or DMARC at the mail server', function (): void {
        $entry = intake($this->mailbox, email($this->desk->requester->email, headers: ['authentication-results' => ['mail.example.com; spf=pass', 'mail.example.com; dmarc=fail (p=none)']]));

        expect($entry->outcome)->toBe(InboundOutcome::IgnoredSpoofed);
    });

    it('limits messages per sender and hour', function (): void {
        config(['ticketing.email.max_per_sender_per_hour' => 2]);

        $outcomes = collect(range(1, 3))->map(fn () => intake($this->mailbox, email($this->desk->requester->email))->outcome);

        expect($outcomes->all())->toBe([InboundOutcome::Created, InboundOutcome::Created, InboundOutcome::IgnoredRateLimited]);
    });

    it('ignores messages that are too large', function (): void {
        config(['ticketing.email.max_message_kilobytes' => 1]);

        expect(intake($this->mailbox, email($this->desk->requester->email))->outcome)->toBe(InboundOutcome::IgnoredTooLarge);
    });
});

describe('reading mailboxes', function (): void {
    it('reads every message in the organization of the mailbox and records the check', function (): void {
        $reader = new FakeMailboxReader([email($this->desk->requester->email), email('stranger@spam.example')]);
        app()->instance(MailboxReader::class, $reader);
        app(OrganizationContext::class)->clear();

        FetchMailbox::dispatchSync($this->mailbox->id);

        app(OrganizationContext::class)->set($this->desk->organization);
        expect($reader->processed)->toHaveCount(2)
            ->and(Ticket::query()->count())->toBe(1)
            ->and($this->mailbox->fresh()->last_checked_at)->not->toBeNull()
            ->and($this->mailbox->fresh()->last_error)->toBeNull();
    });

    it('records why a mailbox could not be read, never the server text', function (): void {
        app()->instance(MailboxReader::class, new FakeMailboxReader(failure: 'authentication'));

        FetchMailbox::dispatchSync($this->mailbox->id);

        expect($this->mailbox->fresh()->last_error)->toBe('authentication');
    });

    it('logs a message that fails, leaves no half-made ticket, and goes on with the next', function (): void {
        // Storing the attachment fails after the ticket was created.
        config(['core.attachments.disk' => 'missing-disk']);
        $reader = new FakeMailboxReader([
            email($this->desk->requester->email, attachments: [new InboundAttachment('screenshot.png', (string) base64_decode(PNG_PIXEL))]),
            email($this->desk->requester->email, subject: 'Second message'),
        ]);
        app()->instance(MailboxReader::class, $reader);

        FetchMailbox::dispatchSync($this->mailbox->id);

        expect($reader->processed)->toHaveCount(2)
            ->and(InboundEmail::query()->get()->map(fn (InboundEmail $entry): string => $entry->outcome->value)->sort()->values()->all())->toBe(['created', 'failed'])
            ->and(Ticket::query()->pluck('title')->all())->toBe(['Second message']);
    });

    it('skips inactive mailboxes', function (): void {
        $reader = new FakeMailboxReader([email($this->desk->requester->email)]);
        app()->instance(MailboxReader::class, $reader);
        $this->mailbox->forceFill(['is_active' => false])->save();

        FetchMailbox::dispatchSync($this->mailbox->id);

        expect($reader->processed)->toBe([]);
    });
});

it('prunes the message log after the retention period', function (): void {
    config(['ticketing.email.retention_days' => 90]);
    $old = InboundEmail::factory()->create(['mailbox_id' => $this->mailbox->id, 'processed_at' => now()->subDays(91)]);
    $recent = InboundEmail::factory()->create(['mailbox_id' => $this->mailbox->id, 'processed_at' => now()->subDays(89)]);

    expect(app(PruneInboundEmails::class)->handle())->toBe(1)
        ->and(InboundEmail::query()->pluck('id')->all())->toBe([$recent->id]);
});

it('puts the reply marker and anti-loop headers in ticket emails', function (): void {
    $mail = (new TicketUpdate('created', 'INC-000001', 'Printer', 'https://example.com', 'helpdesk@example.com'))->toMail($this->desk->requester);

    expect($mail->replyTo)->toBe([['helpdesk@example.com', null]])
        ->and($mail->introLines[0])->toBe(EmailBody::MARKER)
        ->and($mail->callbacks)->toHaveCount(1);
});
