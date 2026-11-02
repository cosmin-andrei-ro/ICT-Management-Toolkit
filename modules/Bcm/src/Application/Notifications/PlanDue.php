<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * A plan is due for review or for an exercise. Plain values only.
 */
final class PlanDue extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'review'|'exercise'  $kind
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $plan,
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
        $replace = ['plan' => $this->plan, 'date' => $this->dueOn];

        return (new MailMessage)
            ->subject(__("bcm::reminders.mail.{$this->kind}.subject", $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__("bcm::reminders.mail.{$this->kind}.line", $replace))
            ->action(__('bcm::reminders.mail.open'), $this->url);
    }
}
