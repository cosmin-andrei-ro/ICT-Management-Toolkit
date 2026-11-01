<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Email;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Ticketing\Domain\Models\InboundEmail;

/**
 * The message log holds email addresses and subjects: kept only as long as
 * needed to investigate intake (GDPR storage limitation).
 */
final readonly class PruneInboundEmails
{
    public function __construct(private Config $config) {}

    public function handle(): int
    {
        $days = $this->config->get('ticketing.email.retention_days');

        if (! is_int($days) || $days <= 0) {
            return 0;
        }

        return InboundEmail::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('processed_at', '<', now()->subDays($days))
            ->toBase()
            ->delete();
    }
}
