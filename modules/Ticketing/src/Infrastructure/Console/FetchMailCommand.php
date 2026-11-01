<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Ticketing\Application\Email\FetchMailbox;
use Modules\Ticketing\Domain\Models\Mailbox;

#[Signature('ticketing:mail:fetch')]
#[Description('Queue reading every active mailbox into tickets')]
final class FetchMailCommand extends Command
{
    public function handle(): int
    {
        $mailboxes = Mailbox::query()->withoutGlobalScope(OrganizationScope::class)->where('is_active', true)->get(['id']);

        $mailboxes->each(fn (Mailbox $mailbox) => FetchMailbox::dispatch($mailbox->id));
        $this->components->info($mailboxes->count().' mailbox(es) queued.');

        return self::SUCCESS;
    }
}
