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
 * Warranties or licences ending soon, one line each. Plain values only: queued
 * notifications run without an organization context.
 */
final class EndingSoon extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'warranty'|'license'  $kind
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $lines,
        public readonly int $more,
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
        $message = (new PlainTextMailMessage)
            ->subject(trans_choice("itam::assets.mail.{$this->kind}_subject", count($this->lines) + $this->more, ['count' => count($this->lines) + $this->more]))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__("itam::assets.mail.{$this->kind}_intro"));

        foreach ($this->lines as $line) {
            $message->line($line);
        }

        if ($this->more > 0) {
            $message->line(__('itam::assets.mail.and_more', ['count' => $this->more]));
        }

        return $message->action(__("itam::assets.mail.{$this->kind}_open"), $this->url);
    }
}
