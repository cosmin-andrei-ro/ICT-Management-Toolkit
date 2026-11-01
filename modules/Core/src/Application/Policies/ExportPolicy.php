<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * An export holds a snapshot of data its requester could see: only they may
 * download it, only while they still hold the permission, until it expires.
 */
final readonly class ExportPolicy
{
    public function __construct(
        private OrganizationContext $context,
        private ExportRegistry $registry,
    ) {}

    public function download(User $user, Export $export): bool
    {
        return $this->context->has()
            && $export->organization_id === $this->context->id()
            && $export->user_id === $user->id
            && $export->isDownloadable()
            && $this->registry->has($export->type)
            && $user->can($this->registry->permission($export->type));
    }
}
