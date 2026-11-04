<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Modules\Core\Domain\Models\User;

/**
 * Published documentation pages for other modules (IT support):
 * suggested for a text, or linked to a record. Implemented by the
 * Documentation module; when it is disabled, none. Only pages the viewer may
 * read are returned.
 */
interface KnowledgeArticles
{
    /**
     * Pages whose words match the text best, e.g. the title of a ticket being raised.
     *
     * @return list<LinkedRecord>
     */
    public function suggest(string $text, User $viewer, int $limit = 5): array;

    /**
     * Pages linked to a record (morph type and id).
     *
     * @return list<LinkedRecord>
     */
    public function about(string $type, string $id, User $viewer): array;
}
