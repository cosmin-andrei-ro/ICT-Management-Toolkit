<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

/**
 * Applications of the portfolio, for modules that refer to them (a risk on an
 * application). Implemented by the Applications module; when it is disabled,
 * nothing is found.
 */
interface Applications
{
    public function isAvailable(): bool;

    /**
     * Applications of the current organization, by name or code.
     *
     * @return list<ApplicationSummary>
     */
    public function search(string $terms, int $limit = 10): array;

    /**
     * @param  list<string>  $ids
     * @return array<string, ApplicationSummary> By id; unknown ids are left out.
     */
    public function find(array $ids): array;
}
