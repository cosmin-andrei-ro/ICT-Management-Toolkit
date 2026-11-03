<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Application\Notifications\PlainTextMailMessage;
use Symfony\Component\Mime\Email;

/**
 * Tells someone they were handed equipment and asks them to confirm receipt.
 * Sent after the commit, so a rolled-back hand-out sends nothing.
 */
final class AssetHandedOver extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $assetTag,
        public readonly string $assetName,
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
        return (new PlainTextMailMessage)
            ->subject(__('itam::handover.mail.subject', ['name' => $this->assetName, 'tag' => $this->assetTag]))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__('itam::handover.mail.line', ['name' => $this->assetName, 'tag' => $this->assetTag]))
            ->action(__('itam::handover.mail.action'), $this->url)
            ->line(__('itam::handover.mail.not_you'));
    }
}
