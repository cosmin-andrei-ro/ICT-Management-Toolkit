<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Application\Notifications\PlainTextMailMessage;
use Symfony\Component\Mime\Email;

/**
 * The weekly state of the continuity plans for their coordinators: counts only.
 */
final class ContinuityDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, int>  $figures  Only the non-zero ones are listed.
     */
    public function __construct(
        public readonly array $figures,
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
        $message = (new PlainTextMailMessage)
            ->subject(__('bcm::reminders.mail.digest.subject'))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            });

        foreach ($this->figures as $key => $count) {
            $message->line(trans_choice('bcm::reminders.mail.digest.'.$key, $count, ['count' => $count]));
        }

        return $message->action(__('bcm::reminders.mail.digest.action'), $this->url);
    }
}
