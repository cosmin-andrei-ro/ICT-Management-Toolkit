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
 * To the project manager: the sponsor approved the gate, or sent it back.
 */
final class GateDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $project,
        public readonly bool $approved,
        public readonly ?string $to,
        public readonly string $sponsor,
        public readonly ?string $note,
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
        $replace = ['project' => $this->project, 'to' => $this->to, 'sponsor' => $this->sponsor];
        $line = match (true) {
            ! $this->approved => __('projects::governance.mail.rejected', $replace),
            $this->to === null => __('projects::governance.mail.closed', $replace),
            default => __('projects::governance.mail.approved', $replace),
        };

        $message = (new MailMessage)
            ->subject(__($this->approved ? 'projects::governance.mail.approved_subject' : 'projects::governance.mail.rejected_subject', $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line($line);

        if ($this->note !== null && trim($this->note) !== '') {
            $message->line(__('projects::governance.mail.note', ['note' => $this->note]));
        }

        return $message->action(__('projects::governance.mail.open'), $this->url);
    }
}
