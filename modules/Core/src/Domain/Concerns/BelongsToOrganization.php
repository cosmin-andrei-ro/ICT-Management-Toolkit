<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Exceptions\CrossOrganizationAccess;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;

/**
 * Scopes a model to the current organization (CLAUDE.md §5.3).
 *
 * organization_id must never be mass assignable: it comes from the context, or is
 * set explicitly by system code (seeders, commands) running without a context.
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            $context = app(OrganizationContext::class);
            $organizationId = $model->getAttribute('organization_id');

            if ($organizationId === null) {
                $model->setAttribute('organization_id', $context->requireId());

                return;
            }

            if ($context->has() && $organizationId !== $context->id()) {
                throw CrossOrganizationAccess::onCreate($model::class);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('organization_id')) {
                throw CrossOrganizationAccess::onMove($model::class);
            }
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
