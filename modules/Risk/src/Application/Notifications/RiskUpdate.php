<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * An acceptance to decide, an acceptance decided, or a risk due for
 * reassessment. Plain values only, sent after the commit.
 */
final class RiskUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'acceptance_requested'|'acceptance_approved'|'acceptance_rejected'|'review_due'  $kind
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $risk,
        public readonly string $url,
        public readonly ?string $detail = null,
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
        $replace = ['risk' => $this->risk, 'detail' => $this->detail ?? ''];
        $message = (new MailMessage)
            ->subject(__("risk::treatment.mail.{$this->kind}.subject", $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__("risk::treatment.mail.{$this->kind}.line", $replace));

        return $message->action(__('risk::treatment.mail.open'), $this->url);
    }
}
