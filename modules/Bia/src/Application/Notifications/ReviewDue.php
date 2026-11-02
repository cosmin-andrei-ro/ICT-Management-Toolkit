<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * A process's impact analysis is due for review. Plain values only.
 */
final class ReviewDue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $process,
        public readonly string $dueOn,
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
        $replace = ['process' => $this->process, 'date' => $this->dueOn];

        return (new MailMessage)
            ->subject(__('bia::reviews.mail.due.subject', $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__('bia::reviews.mail.due.line', $replace))
            ->action(__('bia::reviews.mail.due.action'), $this->url);
    }
}
