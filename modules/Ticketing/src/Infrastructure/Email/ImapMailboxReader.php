<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Infrastructure\Email;

use Carbon\CarbonImmutable;
use Closure;
use DirectoryTree\ImapEngine\Exceptions\ImapCommandException;
use DirectoryTree\ImapEngine\Exceptions\ImapResponseException;
use DirectoryTree\ImapEngine\FolderInterface;
use DirectoryTree\ImapEngine\Mailbox as ImapMailbox;
use DirectoryTree\ImapEngine\Message;
use DirectoryTree\ImapEngine\MessageInterface;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\Ticketing\Application\Email\EmailBody;
use Modules\Ticketing\Application\Email\InboundAttachment;
use Modules\Ticketing\Application\Email\InboundMessage;
use Modules\Ticketing\Application\Email\MailboxReader;
use Modules\Ticketing\Application\Email\MailboxUnavailable;
use Modules\Ticketing\Application\Email\MailServerGuard;
use Modules\Ticketing\Domain\Email\MailEncryption;
use Modules\Ticketing\Domain\Models\Mailbox;
use Throwable;

/**
 * IMAP with directorytree/imapengine. Messages are fetched without setting
 * \Seen, and marked only after they were handled, so a crash re-reads them.
 */
final readonly class ImapMailboxReader implements MailboxReader
{
    /** Headers intake looks at (ProcessInboundEmail). */
    private const array HEADERS = [
        'auto-submitted', 'precedence', 'x-auto-response-suppress', 'x-autoreply', 'x-autorespond',
        'list-id', 'list-unsubscribe', 'return-path', 'content-type', 'authentication-results',
    ];

    public function __construct(
        private Config $config,
        private MailServerGuard $guard,
    ) {}

    public function read(Mailbox $mailbox, int $limit, Closure $handler): int
    {
        $imap = $this->connect($mailbox);

        try {
            $folder = $this->folder($imap, $mailbox->folder);
            $processedFolder = $mailbox->processed_folder !== null ? $this->folder($imap, $mailbox->processed_folder)->path() : null;

            $query = $folder->messages()->withHeaders()->withBody()->withSize()->oldest()->limit($limit);
            // Search criteria are forwarded to the query builder and change the query in place.
            $query->unseen();
            $messages = $query->get();

            $handled = 0;

            foreach ($messages as $message) {
                try {
                    $handler($this->toInbound($message));
                    $handled++;
                } catch (Throwable $exception) {
                    // Unreadable message: marked below all the same, so it does not block the queue.
                    report($exception);
                }

                $message->markSeen();

                // MessageInterface has no move(); messages fetched from a server are always Message.
                if ($processedFolder !== null && $message instanceof Message) {
                    $message->move($processedFolder, expunge: true);
                }
            }

            return $handled;
        } catch (ImapCommandException|ImapResponseException $exception) {
            throw MailboxUnavailable::because('folder', $exception);
        } finally {
            $imap->disconnect();
        }
    }

    public function test(Mailbox $mailbox): void
    {
        $imap = $this->connect($mailbox);

        try {
            $this->folder($imap, $mailbox->folder);

            if ($mailbox->processed_folder !== null) {
                $this->folder($imap, $mailbox->processed_folder);
            }
        } finally {
            $imap->disconnect();
        }
    }

    private function connect(Mailbox $mailbox): ImapMailbox
    {
        $this->guard->assertAllowed($mailbox->host, $mailbox->port, $mailbox->encryption);

        $imap = new ImapMailbox([
            'host' => $mailbox->host,
            'port' => $mailbox->port,
            'encryption' => match ($mailbox->encryption) {
                MailEncryption::Ssl => 'ssl',
                MailEncryption::StartTls => 'starttls',
                MailEncryption::None => '',
            },
            'username' => $mailbox->username,
            'password' => $mailbox->password,
            'validate_cert' => $this->config->get('ticketing.email.verify_certificates') !== false,
            'timeout' => 30,
            // Never configurable: the library treats a class name here as a logger to instantiate.
            'debug' => false,
        ]);

        try {
            $imap->connect();
        } catch (ImapCommandException|ImapResponseException $exception) {
            throw MailboxUnavailable::because('authentication', $exception);
        } catch (Throwable $exception) {
            throw MailboxUnavailable::because('connection', $exception);
        }

        return $imap;
    }

    private function folder(ImapMailbox $imap, string $path): FolderInterface
    {
        return $imap->folders()->find($path) ?? throw MailboxUnavailable::because('folder');
    }

    private function toInbound(MessageInterface $message): InboundMessage
    {
        $headers = [];

        foreach (self::HEADERS as $name) {
            foreach ($message->parse()->getAllHeadersByName($name) as $header) {
                $headers[$name][] = $header->getRawValue();
            }
        }

        $text = $message->text();

        if ($text === null || trim($text) === '') {
            $html = $message->html();
            $text = $html !== null ? EmailBody::fromHtml($html) : '';
        }

        $attachments = [];

        foreach ($message->attachments() as $attachment) {
            $attachments[] = new InboundAttachment(
                (string) ($attachment->filename() ?? 'attachment'),
                $attachment->contents(),
                $attachment->contentDisposition() === 'inline' && $attachment->contentId() !== null,
            );
        }

        $date = $message->date();

        return new InboundMessage(
            uid: $message->uid(),
            messageId: $message->messageId(),
            fromAddress: $message->from()?->email() ?? '',
            subject: (string) $message->subject(),
            text: $text,
            receivedAt: $date !== null ? CarbonImmutable::instance($date) : null,
            size: (int) $message->size(),
            headers: $headers,
            attachments: $attachments,
        );
    }
}
