<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\PageVersion;
use Modules\Documentation\Domain\Models\Space;

/**
 * Writes a page: each save is a new version, kept as it was. Saving a page
 * someone else changed since it was opened is refused, so no change is lost.
 */
final readonly class SavePage
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  ?int  $baseVersion  The version the editor started from.
     *
     * @throws PageChangedMeanwhile
     * @throws InvalidArgumentException for a parent outside the space or below the page itself
     */
    public function handle(?Page $page, Space $space, ?string $parentId, string $title, string $body, ?string $summary, ?int $baseVersion, User $author): Page
    {
        return $this->db->transaction(function () use ($page, $space, $parentId, $title, $body, $summary, $baseVersion, $author): Page {
            if ($page !== null) {
                $page = Page::query()->lockForUpdate()->findOrFail($page->id);

                if ($baseVersion !== null && $page->version !== $baseVersion) {
                    throw new PageChangedMeanwhile;
                }
            }

            $this->assertParent($space, $page, $parentId);
            $title = trim($title);
            // Line endings as typed in any browser, compared and shown the same way.
            $body = str_replace(["\r\n", "\r"], "\n", $body);

            if ($page !== null && $page->title === $title && $page->body === $body && $page->parent_id === $parentId) {
                return $page;
            }

            $version = $page !== null ? $page->version + 1 : 1;
            $page ??= new Page;
            $page->forceFill([
                'organization_id' => $space->organization_id,
                'space_id' => $space->id,
                'parent_id' => $parentId,
                'title' => $title,
                'body' => $body,
                'version' => $version,
            ]);

            if (! $page->exists) {
                $position = Page::query()->where('space_id', $space->id)->where('parent_id', $parentId)->max('position');
                $page->position = (is_numeric($position) ? (int) $position : -1) + 1;
            }

            $page->save();

            $record = new PageVersion;
            $record->forceFill([
                'page_id' => $page->id,
                'version' => $version,
                'title' => $title,
                'body' => $body,
                'summary' => $summary !== null && trim($summary) !== '' ? mb_substr(trim($summary), 0, 500) : null,
                'author_id' => $author->id,
            ])->save();

            return $page;
        });
    }

    private function assertParent(Space $space, ?Page $page, ?string $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = Page::query()->where('space_id', $space->id)->find($parentId);

        // Walk up from the new parent: meeting the page itself would make a loop.
        for ($ancestor = $parent, $depth = 0; $ancestor !== null && $depth < 50; $ancestor = $ancestor->parent, $depth++) {
            if ($page !== null && $ancestor->id === $page->id) {
                throw new InvalidArgumentException('A page cannot go below itself.');
            }
        }

        if ($parent === null) {
            throw new InvalidArgumentException('Unknown parent page.');
        }
    }
}
