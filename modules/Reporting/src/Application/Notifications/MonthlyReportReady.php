<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * The monthly management report: how many indicators need attention, a link
 * to the report and, where the PDF service runs, the report as a PDF.
 */
final class MonthlyReportReady extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, int>  $statuses  Indicators by status at the end of the month.
     * @param  ?string  $pdf  The PDF, base64-encoded so the queued message stays text.
     */
    public function __construct(
        public readonly string $organization,
        public readonly string $month,
        public readonly array $statuses,
        public readonly string $url,
        public readonly ?string $pdf = null,
        public readonly string $filename = 'management-report.pdf',
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
        $message = (new MailMessage)
            ->subject(__('reporting::management.mail.subject', ['month' => $this->month, 'organization' => $this->organization]))
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');
            })
            ->line(__('reporting::management.mail.intro', ['month' => $this->month]));

        foreach (['alert', 'warning'] as $status) {
            $count = $this->statuses[$status] ?? 0;

            if ($count > 0) {
                $message->line(trans_choice('reporting::management.mail.'.$status, $count, ['count' => $count]));
            }
        }

        $message->action(__('reporting::management.mail.action'), $this->url);

        $pdf = $this->pdf !== null ? base64_decode($this->pdf, true) : false;

        if ($pdf !== false) {
            $message->attachData($pdf, $this->filename, ['mime' => 'application/pdf']);
        }

        return $message;
    }
}
