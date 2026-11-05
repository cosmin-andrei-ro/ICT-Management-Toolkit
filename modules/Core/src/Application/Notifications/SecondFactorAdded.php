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
 * Tells the person a new authenticator app was set up on their account, so that
 * someone doing it from a stolen session does not go unnoticed.
 */
final class SecondFactorAdded extends Notification implements ShouldQueue
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
            ->subject(__('core::security.mail.second_factor.subject', ['app' => $app]))
            ->line(__('core::security.mail.second_factor.line', ['app' => $app]))
            ->line(__('core::security.mail.second_factor.not_you'));
    }
}
