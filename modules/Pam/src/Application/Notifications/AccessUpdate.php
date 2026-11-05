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
 * Requests, decisions and reminders. The account's name and a link, never a credential.
 */
final class AccessUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $kind,
        public readonly string $accountName,
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
            ->subject(__('pam::accounts.mail.'.$this->kind.'.subject'))
            ->line(__('pam::accounts.mail.'.$this->kind.'.line', ['account' => $this->accountName]))
            ->action(__('pam::accounts.mail.open'), $this->url);
    }
}
