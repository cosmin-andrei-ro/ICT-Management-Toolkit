<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * A control the recipient owns is due for its periodic test. Plain values
 * only, sent after the commit.
 */
final class ControlTestDue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $control,
        public readonly string $url,
        public readonly string $dueOn,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = ['control' => $this->control, 'date' => $this->dueOn];

        return (new MailMessage)
            ->subject(__('audit::evidence.mail.subject', $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__('audit::evidence.mail.line', $replace))
            ->action(__('audit::evidence.mail.open'), $this->url);
    }
}
