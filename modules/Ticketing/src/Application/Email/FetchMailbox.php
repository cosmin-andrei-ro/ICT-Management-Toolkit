<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Email;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Ticketing\Domain\Models\Mailbox;
use Throwable;

/**
 * Reads one mailbox. Unique per mailbox, so two runs never handle the same
 * messages at once.
 */
final class FetchMailbox implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(public readonly string $mailboxId) {}

    public function uniqueId(): string
    {
        return $this->mailboxId;
    }

    public function handle(OrganizationContext $context, MailboxReader $reader, ProcessInboundEmail $process, Config $config): void
    {
        $mailbox = Mailbox::query()->withoutGlobalScope(OrganizationScope::class)->find($this->mailboxId);

        if ($mailbox === null || ! $mailbox->is_active) {
            return;
        }

        $limit = $config->get('ticketing.email.messages_per_run');

        $context->runAs($mailbox->organization_id, function () use ($mailbox, $reader, $process, $limit): void {
            try {
                $reader->read($mailbox, is_int($limit) && $limit > 0 ? $limit : 50, function (InboundMessage $message) use ($mailbox, $process): void {
                    try {
                        $process->handle($mailbox, $message);
                    } catch (Throwable $exception) {
                        // Logged as failed and marked read: marking it unread in the mailbox retries it.
                        report($exception);
                        $process->logFailure($mailbox, $message, class_basename($exception));
                    }
                });
                $error = null;
            } catch (MailboxUnavailable $exception) {
                $error = $exception->reason;
                Log::warning('Mailbox could not be read.', ['mailbox_id' => $mailbox->id, 'reason' => $error]);
            }

            // Status only: no audit entry, no author change.
            $mailbox->forceFill(['last_checked_at' => now(), 'last_error' => $error])->saveQuietly();
        });
    }
}
