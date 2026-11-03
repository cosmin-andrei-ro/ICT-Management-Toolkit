<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the person their account was locked after failed sign-ins, so a lock
 * caused by someone else does not go unnoticed, and how to unlock it.
 */
final class AccountLocked extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = config('app.name');
        $app = is_string($name) ? $name : '';

        return (new PlainTextMailMessage)
            ->subject(__('core::security.mail.locked.subject', ['app' => $app]))
            ->line(__('core::security.mail.locked.line', ['app' => $app]))
            ->line(__('core::security.mail.locked.unlock'))
            ->action(__('core::security.mail.locked.action'), route('password.request'))
            ->line(__('core::security.mail.locked.not_you'));
    }
}
