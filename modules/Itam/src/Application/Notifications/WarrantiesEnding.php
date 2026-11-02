<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * Warranties ending soon, one line per asset (tag, name, end date). Plain values
 * only: queued notifications run without an organization context.
 */
final class WarrantiesEnding extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly array $lines,
        public readonly int $more,
        public readonly string $url,
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
            ->subject(trans_choice('itam::assets.mail.warranty_subject', count($this->lines) + $this->more, ['count' => count($this->lines) + $this->more]))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__('itam::assets.mail.warranty_intro'));

        foreach ($this->lines as $line) {
            $message->line($line);
        }

        if ($this->more > 0) {
            $message->line(__('itam::assets.mail.and_more', ['count' => $this->more]));
        }

        return $message->action(__('itam::assets.mail.open'), $this->url);
    }
}
