<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email about a ticket. Carries only plain values (no model): queued notifications
 * run without an organization context, and comment bodies are never emailed.
 */
final class TicketUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $kind,
        public readonly string $number,
        public readonly string $title,
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
        return (new MailMessage)
            ->subject(__("ticketing::notifications.{$this->kind}.subject", ['number' => $this->number]))
            ->line(__("ticketing::notifications.{$this->kind}.line", ['number' => $this->number]))
            ->line($this->title)
            ->action(__('ticketing::notifications.open_ticket'), $this->url);
    }
}
