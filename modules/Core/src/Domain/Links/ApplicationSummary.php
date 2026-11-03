<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

/**
 * An application of the portfolio as other modules see it.
 */
final readonly class ApplicationSummary
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $url,
        /** Key of the organization's business criticality list; null while not documented. */
        public ?string $criticality,
        /** A version of its documentation sheet has been approved. */
        public bool $documented = false,
        /** Its own CMDB item. */
        public ?string $configurationItemId = null,
        /** @var list<string> Business processes it supports, from the approved documentation. */
        public array $processIds = [],
    ) {}
}
