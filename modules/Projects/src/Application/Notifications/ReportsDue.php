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
 * Weekly, to a project manager: the projects whose status report is due.
 */
final class ReportsDue extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{name: string, url: string}>  $projects
     */
    public function __construct(public readonly array $projects) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(trans_choice('projects::governance.mail.reports_subject', count($this->projects), ['count' => count($this->projects)]))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__('projects::governance.mail.reports_intro'));

        foreach ($this->projects as $project) {
            $message->line('- '.$project['name'].': '.$project['url']);
        }

        return $message;
    }
}
