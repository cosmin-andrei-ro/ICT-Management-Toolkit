<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

use Modules\Core\Domain\Models\User;

/**
 * An application's documentation came into force (CLAUDE.md §9): other
 * modules keep what they derive from it up to date - the documentation page
 * of the application, for one. Dispatched inside the approval's transaction.
 */
final readonly class ApplicationDocumented
{
    /**
     * @param  list<array{role: string, name: string}>  $owners  Labels already translated.
     * @param  list<string>  $ownerIds  The people who keep the application's documentation.
     * @param  list<array{label: string, value: string}>  $support
     * @param  list<string>  $components  Names of its CMDB items.
     * @param  list<array{name: string, rto: ?string, rpo: ?string}>  $processes  Business processes it supports, durations formatted.
     */
    public function __construct(
        public string $organizationId,
        public string $applicationId,
        public ?string $configurationItemId,
        public string $code,
        public string $name,
        public string $version,
        public string $description,
        public ?string $criticality,
        public array $owners,
        public array $ownerIds,
        public array $support,
        public array $components,
        public array $processes,
        public string $applicationUrl,
        public string $documentUrl,
        public User $actor,
    ) {}
}
