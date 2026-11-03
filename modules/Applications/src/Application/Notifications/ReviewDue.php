<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Application\Notifications\PlainTextMailMessage;
use Symfony\Component\Mime\Email;

/**
 * An application's documentation is due for review. Plain values only.
 */
final class ReviewDue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $application,
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
        $replace = ['application' => $this->application, 'date' => $this->dueOn];

        return (new PlainTextMailMessage)
            ->subject(__('applications::applications.mail.review.subject', $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__('applications::applications.mail.review.line', $replace))
            ->action(__('applications::applications.mail.open'), $this->url);
    }
}
