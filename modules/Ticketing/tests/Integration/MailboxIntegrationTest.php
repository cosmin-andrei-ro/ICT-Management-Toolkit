<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Modules\Ticketing\Application\Email\FetchMailbox;
use Modules\Ticketing\Application\Email\InboundMessage;
use Modules\Ticketing\Application\Email\MailboxReader;
use Modules\Ticketing\Application\Email\MailboxUnavailable;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Models\Mailbox;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Tests\Support\TicketingScenario;

/*
 * Runs against the development GreenMail container (docs/development/email-to-ticket.md):
 *   COMPOSE_PROFILES=mail ./vendor/bin/sail up -d
 *   ./vendor/bin/sail exec -e MAIL_INTEGRATION=1 laravel.test php vendor/bin/pest modules/Ticketing/tests/Integration
 * Skipped unless MAIL_INTEGRATION is set.
 */

beforeEach(function (): void {
    if (getenv('MAIL_INTEGRATION') === false) {
        $this->markTestSkipped('Set MAIL_INTEGRATION=1 and start the GreenMail container to run these tests.');
    }

    config([
        'ticketing.email.allowed_ports' => [3143],
        'ticketing.email.allow_unencrypted' => true,
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'greenmail',
        'mail.mailers.smtp.port' => 3025,
        'mail.mailers.smtp.scheme' => 'smtp',
        'mail.mailers.smtp.username' => null,
        'mail.mailers.smtp.password' => null,
    ]);

    $this->desk = TicketingScenario::create();
    $this->mailbox = Mailbox::factory()->create([
        'address' => 'helpdesk@corp.example', 'host' => 'greenmail', 'port' => 3143, 'encryption' => 'none',
        'username' => 'helpdesk@corp.example', 'password' => 'helpdesk-dev-only',
    ]);
});

function sendToHelpdesk(string $from, string $subject, string $body): void
{
    Mail::raw($body, fn (Message $message) => $message->from($from)->to('helpdesk@corp.example')->subject($subject));
}

it('reads a real message over IMAP and marks it as read', function (): void {
    $subject = 'Integration '.uniqid();
    sendToHelpdesk($this->desk->requester->email, $subject, 'Monitor flickers.');

    $seen = [];
    app(MailboxReader::class)->read($this->mailbox, 50, function (InboundMessage $message) use (&$seen): void {
        $seen[$message->subject] = $message;
    });

    expect($seen)->toHaveKey($subject)
        ->and(trim($seen[$subject]->text))->toBe('Monitor flickers.')
        ->and(mb_strtolower($seen[$subject]->fromAddress))->toBe(mb_strtolower($this->desk->requester->email));

    $again = [];
    app(MailboxReader::class)->read($this->mailbox, 50, function (InboundMessage $message) use (&$again): void {
        $again[] = $message->subject;
    });
    expect($again)->not->toContain($subject);
});

it('turns a real message into a ticket', function (): void {
    Notification::fake();
    $subject = 'Ticket '.uniqid();
    sendToHelpdesk($this->desk->requester->email, $subject, 'Keyboard missing keys.');

    FetchMailbox::dispatchSync($this->mailbox->id);

    $ticket = Ticket::query()->where('title', $subject)->sole();
    expect($ticket->channel)->toBe(TicketChannel::Email)
        ->and($this->mailbox->fresh()->last_error)->toBeNull();
});

it('reports a wrong password as an authentication failure', function (): void {
    $this->mailbox->forceFill(['password' => 'wrong'])->save();

    expect(fn () => app(MailboxReader::class)->test($this->mailbox))
        ->toThrow(fn (MailboxUnavailable $exception) => expect($exception->reason)->toBe('authentication'));
});

it('moves processed messages to the configured folder', function (): void {
    $imap = new DirectoryTree\ImapEngine\Mailbox(['host' => 'greenmail', 'port' => 3143, 'encryption' => '', 'username' => 'helpdesk@corp.example', 'password' => 'helpdesk-dev-only']);
    $imap->folders()->firstOrCreate('Processed');
    $this->mailbox->forceFill(['processed_folder' => 'Processed'])->save();
    $subject = 'Moved '.uniqid();
    sendToHelpdesk($this->desk->requester->email, $subject, 'Move me.');

    app(MailboxReader::class)->read($this->mailbox, 50, fn () => null);

    $subjects = fn (string $folder): array => $imap->folders()->findOrFail($folder)->messages()->withHeaders()->get()->map(fn ($message) => $message->subject())->all();
    expect($subjects('Processed'))->toContain($subject)
        ->and($subjects('INBOX'))->not->toContain($subject);
});
