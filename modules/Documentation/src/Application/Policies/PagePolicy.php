<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Policies;

use Modules\Core\Domain\Models\User;
use Modules\Documentation\Domain\Models\Page;

/**
 * A page is read and written by whoever reads and writes its space. Its
 * attachments follow (Core's attachment download); pages have no comments.
 */
final readonly class PagePolicy
{
    public function __construct(private SpacePolicy $spaces) {}

    public function view(User $user, Page $page): bool
    {
        return $this->spaces->view($user, $page->space);
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
