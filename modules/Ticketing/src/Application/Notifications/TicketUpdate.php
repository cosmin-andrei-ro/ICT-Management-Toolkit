<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Ticketing\Application\Email\EmailBody;
use Symfony\Component\Mime\Email;

/**
 * Email about a ticket. Carries only plain values (no model): queued notifications
 * run without an organization context, and comment bodies are never emailed.
 */
final class TicketUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $kind,
        public readonly string $number,
        public readonly string $title,
        public readonly string $url,
        /** The organization's support mailbox: replies by email become ticket comments. */
        public readonly ?string $replyTo = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__("ticketing::notifications.{$this->kind}.subject", ['number' => $this->number]))
            // Out-of-office replies and other robots must not answer (RFC 3834, Exchange).
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            });

        // "??": notifications queued by an older version have no replyTo at all.
        $replyTo = $this->replyTo ?? null;

        if ($replyTo !== null) {
            $message->replyTo($replyTo)
                ->line(EmailBody::MARKER)
                ->line(__('ticketing::notifications.reply_by_email'));
        }

        return $message
            ->line(__("ticketing::notifications.{$this->kind}.line", ['number' => $this->number]))
            ->line($this->title)
            ->action(__('ticketing::notifications.open_ticket'), $this->url);
    }
}
