<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

/**
 * Password reset or invitation link. Queued, so the response time of "forgot
 * password" does not reveal whether an account exists.
 */
final class PasswordLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        #[SensitiveParameter] public readonly string $token,
        public readonly bool $invitation = false,
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
        $email = $notifiable instanceof CanResetPassword ? $notifiable->getEmailForPasswordReset() : '';
        $kind = $this->invitation ? 'invitation' : 'reset';
        $url = route($this->invitation ? 'invitation.accept' : 'password.reset', ['token' => $this->token, 'email' => $email]);
        $name = config('app.name');

        return (new MailMessage)
            ->subject(__("core::security.mail.{$kind}.subject", ['app' => is_string($name) ? $name : '']))
            ->line(__("core::security.mail.{$kind}.line", ['app' => is_string($name) ? $name : '']))
            ->action(__("core::security.mail.{$kind}.action"), $url)
            ->line(__("core::security.mail.{$kind}.expiry"))
            ->line(__('core::security.mail.ignore'));
    }
}
