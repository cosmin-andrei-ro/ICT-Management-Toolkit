<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Application\Notifications\PlainTextMailMessage;
use Symfony\Component\Mime\Email;

/**
 * The weekly state of the register for risk managers: counts only.
 */
final class RiskDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, int>  $figures  Only the non-zero ones.
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
            ->subject(__('risk::report.digest.subject'))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            });

        foreach ($this->figures as $key => $count) {
            $message->line(trans_choice('risk::report.digest.'.$key, $count, ['count' => $count]));
        }

        return $message->action(__('risk::report.digest.action'), $this->url);
    }
}
