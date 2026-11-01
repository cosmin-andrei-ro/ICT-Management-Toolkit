<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Collaboration;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Core\Domain\Exceptions\CrossOrganizationAccess;
use Modules\Core\Domain\Models\Comment;
use Modules\Core\Domain\Models\User;

/**
 * Adds a comment to any entity. Authorization is the caller's job (the parent's
 * "comment" / "commentInternally" abilities); this action guards integrity.
 */
final class AddComment
{
    public const int MAX_LENGTH = 20_000;

    public function handle(Model $parent, User $author, string $body, bool $internal): Comment
    {
        $body = trim($body);

        if ($body === '' || mb_strlen($body) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('A comment must be between 1 and '.self::MAX_LENGTH.' characters.');
        }

        $organizationId = $parent->getAttribute('organization_id');

        if (! is_string($organizationId)) {
            throw CrossOrganizationAccess::onCreate(Comment::class);
        }

        $comment = new Comment;
        $comment->forceFill([
            'organization_id' => $organizationId,
            'commentable_type' => $parent->getMorphClass(),
            'commentable_id' => $parent->getKey(),
            'author_id' => $author->id,
            'body' => $body,
            'is_internal' => $internal,
        ])->save();

        return $comment;
    }
}
