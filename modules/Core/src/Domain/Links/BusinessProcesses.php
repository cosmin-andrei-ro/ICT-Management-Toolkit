<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

/**
 * Business processes of the BIA, for modules that plan for them (continuity
 * plans). Implemented by the BIA module; when it is disabled, nothing is found.
 */
interface BusinessProcesses
{
    public function isAvailable(): bool;

    /**
     * Active processes of the current organization, by name.
     *
     * @return list<ProcessSummary>
     */
    public function search(string $terms, int $limit = 10): array;

    /**
     * @param  list<string>  $ids
     * @return array<string, ProcessSummary> By id; unknown ids are left out.
     */
    public function find(array $ids): array;

    /**
     * Every active process with an approved assessment, strictest RTO first.
     *
     * @return list<ProcessSummary>
     */
    public function assessed(): array;
}
