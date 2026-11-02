<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Sso;

use Modules\Core\Domain\Models\User;
use RuntimeException;

/**
 * Single sign-on did not go through. The reason is for the audit trail and
 * the log; the person sees a general message.
 */
final class SsoFailed extends RuntimeException
{
    /** The account concerned, when there is one. */
    public ?User $user = null;

    public static function because(string $reason, ?User $user = null): self
    {
        $failure = new self($reason);
        $failure->user = $user;

        return $failure;
    }
}
