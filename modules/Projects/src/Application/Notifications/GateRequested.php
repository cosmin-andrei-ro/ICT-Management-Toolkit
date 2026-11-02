<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * To the sponsor: a project asks to move on, or to close.
 */
final class GateRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $project,
        public readonly string $from,
        public readonly ?string $to,
        public readonly string $requester,
        public readonly string $url,
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
        $replace = ['project' => $this->project, 'from' => $this->from, 'to' => $this->to, 'requester' => $this->requester];

        return (new MailMessage)
            ->subject(__('projects::governance.mail.requested_subject', $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line($this->to !== null ? __('projects::governance.mail.requested_move', $replace) : __('projects::governance.mail.requested_close', $replace))
            ->action(__('projects::governance.mail.open'), $this->url);
    }
}
