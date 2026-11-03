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
 * A plan version was submitted, approved or returned. Plain values only, sent
 * after the commit; the plan's content is not emailed.
 */
final class PlanUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'submitted'|'approved'|'returned'  $kind
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $plan,
        public readonly int $version,
        public readonly string $url,
        public readonly ?string $comment = null,
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
        $replace = ['plan' => $this->plan, 'version' => $this->version];
        $message = (new PlainTextMailMessage)
            ->subject(__("bcm::plans.mail.{$this->kind}.subject", $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__("bcm::plans.mail.{$this->kind}.line", $replace));

        if ($this->comment !== null) {
            $message->line(__('bcm::plans.mail.comment', ['comment' => $this->comment]));
        }

        return $message->action(__('bcm::plans.mail.open'), $this->url);
    }
}
