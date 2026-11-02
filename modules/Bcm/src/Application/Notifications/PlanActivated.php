<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * A plan was activated: the crisis team is told and pointed to the plan.
 * Plain values only, sent after the commit; the plan's content is not emailed.
 */
final class PlanActivated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $plan,
        public readonly string $activatedAt,
        public readonly string $activatedBy,
        public readonly string $planUrl,
        public readonly string $activationUrl,
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
        $timezone = config('app.display_timezone');
        $replace = [
            'plan' => $this->plan,
            'name' => $this->activatedBy,
            'date' => CarbonImmutable::parse($this->activatedAt)->setTimezone(is_string($timezone) && $timezone !== '' ? $timezone : 'UTC')->format('Y-m-d H:i'),
        ];

        return (new MailMessage)
            ->subject(__('bcm::activations.mail.subject', $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
                $email->priority(Email::PRIORITY_HIGH);
            })
            ->line(__('bcm::activations.mail.line', $replace))
            ->action(__('bcm::activations.mail.plan'), $this->planUrl)
            ->line(__('bcm::activations.mail.record', ['url' => $this->activationUrl]));
    }
}
