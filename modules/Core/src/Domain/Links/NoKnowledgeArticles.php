<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Modules\Core\Domain\Models\User;

/**
 * Without the Documentation module there is nothing to suggest.
 */
final readonly class NoKnowledgeArticles implements KnowledgeArticles
{
    public function suggest(string $text, User $viewer, int $limit = 5): array
    {
        return [];
    }

    public function about(string $type, string $id, User $viewer): array
    {
        return [];
    }
}
