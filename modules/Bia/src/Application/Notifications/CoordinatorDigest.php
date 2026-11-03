<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Application\Notifications\PlainTextMailMessage;
use Symfony\Component\Mime\Email;

/**
 * The weekly state of the BIA for its coordinators: counts only.
 */
final class CoordinatorDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $overdue,
        public readonly int $neverAssessed,
        public readonly int $awaitingApproval,
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
        return (new PlainTextMailMessage)
            ->subject(__('bia::reviews.mail.digest.subject'))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__('bia::reviews.mail.digest.overdue', ['count' => $this->overdue]))
            ->line(__('bia::reviews.mail.digest.never_assessed', ['count' => $this->neverAssessed]))
            ->line(__('bia::reviews.mail.digest.awaiting_approval', ['count' => $this->awaitingApproval]))
            ->action(__('bia::reviews.mail.digest.action'), $this->url);
    }
}
