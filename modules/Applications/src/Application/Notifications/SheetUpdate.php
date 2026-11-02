<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * A sheet version was submitted, returned or came into force. Plain values
 * only, sent after the commit; the sheet's content is not emailed.
 */
final class SheetUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'submitted'|'returned'|'in_force'  $kind
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $application,
        public readonly string $url,
        public readonly ?string $version = null,
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
        $replace = ['application' => $this->application, 'version' => $this->version ?? ''];
        $message = (new MailMessage)
            ->subject(__("applications::applications.mail.{$this->kind}.subject", $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__("applications::applications.mail.{$this->kind}.line", $replace));

        if ($this->comment !== null && $this->comment !== '') {
            $message->line(__('applications::applications.mail.comment', ['comment' => $this->comment]));
        }

        return $message->action(__('applications::applications.mail.open'), $this->url);
    }
}
