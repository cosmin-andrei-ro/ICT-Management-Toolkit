<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * Email before a contract's deadline. Plain values only (no model): queued
 * notifications run without an organization context. The value of the contract
 * is never emailed.
 */
final class ContractDeadlineApproaching extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $supplier,
        public readonly ?string $reference,
        public readonly string $deadline,
        public readonly string $endDate,
        public readonly bool $autoRenews,
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
        $passed = CarbonImmutable::parse($this->deadline)->isPast() && ! CarbonImmutable::parse($this->deadline)->isToday();
        $replace = [
            'title' => $this->title,
            'supplier' => $this->supplier,
            'deadline' => $this->deadline,
            'end' => $this->endDate,
        ];

        $message = (new PlainTextMailMessage)
            ->subject(__('core::contracts.mail.subject', $replace))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__($passed ? 'core::contracts.mail.deadline_passed' : 'core::contracts.mail.deadline', $replace))
            ->line(__($this->autoRenews ? 'core::contracts.mail.renews' : 'core::contracts.mail.ends', $replace));

        if ($this->reference !== null) {
            $message->line(__('core::contracts.mail.reference', ['reference' => $this->reference]));
        }

        return $message->action(__('core::contracts.mail.open'), $this->url);
    }
}
