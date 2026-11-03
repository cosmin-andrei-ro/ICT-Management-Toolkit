<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Application\Notifications\PlainTextMailMessage;
use Symfony\Component\Mime\Email;

/**
 * An assessment was submitted, approved or returned. Plain values only, sent
 * after the commit; the impact scores and objectives are not emailed.
 */
final class AssessmentUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'submitted'|'approved'|'returned'  $kind
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $process,
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
        $replace = ['process' => $this->process, 'version' => $this->version];
        $message = (new PlainTextMailMessage)
            ->subject(__("bia::assessments.mail.{$this->kind}.subject", $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__("bia::assessments.mail.{$this->kind}.line", $replace));

        if ($this->comment !== null) {
            $message->line(__('bia::assessments.mail.comment', ['comment' => $this->comment]));
        }

        return $message->action(__('bia::assessments.mail.open'), $this->url);
    }
}
