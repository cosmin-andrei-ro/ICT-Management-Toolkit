<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Application\Notifications\PlainTextMailMessage;
use Symfony\Component\Mime\Email;

/**
 * A finding assigned, submitted for verification, closed or reopened. Plain
 * values only, sent after the commit.
 */
final class FindingUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'assigned'|'submitted'|'closed'|'reopened'  $kind
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $finding,
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
        $replace = ['finding' => $this->finding, 'detail' => $this->detail ?? '—'];

        return (new PlainTextMailMessage)
            ->subject(__("audit::audits.mail.{$this->kind}.subject", $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__("audit::audits.mail.{$this->kind}.line", $replace))
            ->action(__('audit::audits.mail.open'), $this->url);
    }
}
