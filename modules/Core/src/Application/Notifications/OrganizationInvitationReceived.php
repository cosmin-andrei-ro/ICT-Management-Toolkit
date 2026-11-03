<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Domain\Models\OrganizationInvitation;

/**
 * Asks someone who already has an account whether to join another
 * organization. The link only opens their invitations: accepting needs them
 * signed in. Queued, like every invitation, so the answer to the inviting
 * administrator takes the same time whoever is invited.
 */
final class OrganizationInvitationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $organization) {}

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

        return (new MailMessage)
            ->subject(__('core::invitations.mail.subject', ['app' => $app]))
            ->line(__('core::invitations.mail.line', ['organization' => $this->organization, 'app' => $app]))
            ->action(__('core::invitations.mail.action'), route('account.invitations'))
            ->line(__('core::invitations.mail.expiry', ['days' => OrganizationInvitation::DAYS_VALID]))
            ->line(__('core::invitations.mail.ignore'));
    }
}
