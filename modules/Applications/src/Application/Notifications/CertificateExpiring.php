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
 * A certificate an application uses expires soon. Plain values only.
 */
final class CertificateExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $application,
        public readonly string $certificate,
        public readonly string $expiresOn,
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
        $replace = ['application' => $this->application, 'certificate' => $this->certificate, 'date' => $this->expiresOn];

        return (new MailMessage)
            ->subject(__('applications::applications.mail.certificate.subject', $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__('applications::applications.mail.certificate.line', $replace))
            ->action(__('applications::applications.mail.open'), $this->url);
    }
}
