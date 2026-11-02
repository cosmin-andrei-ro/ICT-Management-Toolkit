<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Policies;

use Modules\Core\Domain\Models\User;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\PageVersion;

/**
 * A page is read by whoever reads its space once it is published, and written
 * by whoever writes in its space. Its attachments follow (Core's attachment
 * download); pages have no comments.
 */
final readonly class PagePolicy
{
    public function __construct(private SpacePolicy $spaces) {}

    public function view(User $user, Page $page): bool
    {
        return $this->spaces->view($user, $page->space) && ($page->isPublished() || $this->update($user, $page));
    }

    /**
     * Drafts of controlled spaces stay with their editors; in free spaces every version was published.
     */
    public function viewHistory(User $user, Page $page): bool
    {
        return $this->view($user, $page) && (! $page->space->requires_approval || $this->update($user, $page));
    }

    /**
     * A space owner who neither wrote the version waiting nor submitted it.
     */
    public function approve(User $user, Page $page): bool
    {
        if ($page->pending_version === null || $page->submitted_by === $user->id || ! $this->spaces->approve($user, $page->space)) {
            return false;
        }

        $author = PageVersion::query()->where('page_id', $page->id)->where('version', $page->pending_version)->value('author_id');

        return $author !== $user->id;
    }

    public function update(User $user, Page $page): bool
    {
        return $this->spaces->edit($user, $page->space);
    }

    public function delete(User $user, Page $page): bool
    {
        return $this->update($user, $page);
    }

    public function viewInternal(User $user, Page $page): bool
    {
        return false;
    }

    public function comment(User $user, Page $page): bool
    {
        return false;
    }

    public function commentInternally(User $user, Page $page): bool
    {
        return false;
    }
}
