<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Application\Notifications\PlainTextMailMessage;

/**
 * Someone received access to a credential. It goes to the person, the owner and
 * those who manage the vault: one person with two accounts should not go unseen.
 */
final class AccessGranted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $accountName,
        public readonly string $grantedTo,
        public readonly string $grantedBy,
        public readonly string $url,
    ) {}

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
            ->subject(__('pam::accounts.mail.granted.subject'))
            ->line(__('pam::accounts.mail.granted.line', ['to' => $this->grantedTo, 'account' => $this->accountName, 'by' => $this->grantedBy]))
            ->action(__('pam::accounts.mail.granted.action'), $this->url)
            ->line(__('pam::accounts.mail.granted.not_expected'));
    }
}
