<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use Illuminate\Contracts\Auth\Access\Gate;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * An attachment is visible to whoever may see its parent; internal ones only to
 * those allowed to see the parent's internal notes.
 */
final readonly class AttachmentPolicy
{
    public function __construct(
        private Gate $gate,
        private OrganizationContext $context,
    ) {}

    public function view(User $user, Attachment $attachment): bool
    {
        if (! $this->context->has() || $attachment->organization_id !== $this->context->id()) {
            return false;
        }

        $parent = $attachment->attachable;

        if ($parent === null) {
            return false;
        }

        return $this->gate->forUser($user)->allows($attachment->is_internal ? 'viewInternal' : 'view', $parent);
    }
}
