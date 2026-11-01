<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Email;

use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Ticketing\Domain\Models\Mailbox;

/**
 * Where replies to ticket emails should go: the organization's first active
 * mailbox, if it has one.
 */
final class ReplyAddress
{
    /** @var array<string, ?string> */
    private array $resolved = [];

    public function for(string $organizationId): ?string
    {
        if (! array_key_exists($organizationId, $this->resolved)) {
            $address = Mailbox::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->value('address');

            $this->resolved[$organizationId] = is_string($address) ? $address : null;
        }

        return $this->resolved[$organizationId];
    }
}
