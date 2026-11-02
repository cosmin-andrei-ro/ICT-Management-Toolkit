<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Documentation\Domain\Models\Page;

/**
 * Deletes a page (recoverable: soft delete, versions kept). Its sub-pages
 * move up to its parent rather than disappearing with it.
 */
final readonly class DeletePage
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(Page $page): void
    {
        $this->db->transaction(function () use ($page): void {
            foreach ($page->children()->get() as $child) {
                $child->forceFill(['parent_id' => $page->parent_id])->save();
            }

            $page->delete();
        });
    }
}
