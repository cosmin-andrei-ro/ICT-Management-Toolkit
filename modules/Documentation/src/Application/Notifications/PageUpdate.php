<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Application\Notifications\PlainTextMailMessage;
use Symfony\Component\Mime\Email;

/**
 * A page submitted for approval, approved or rejected. Plain
 * values only, sent after the commit.
 */
final class PageUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'submitted'|'approved'|'rejected'  $kind
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $page,
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
        $replace = ['page' => $this->page, 'detail' => $this->detail ?? '—'];

        return (new PlainTextMailMessage)
            ->subject(__("documentation::docs.mail.{$this->kind}.subject", $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__("documentation::docs.mail.{$this->kind}.line", $replace))
            ->action(__('documentation::docs.mail.open'), $this->url);
    }
}
