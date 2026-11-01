<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
final class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Resolved per query, not per boot: the context changes between requests and jobs.
        $organizationId = app(OrganizationContext::class)->id();

        if ($organizationId === null) {
            $builder->whereRaw('false');

            return;
        }

        $builder->where($model->qualifyColumn('organization_id'), $organizationId);
    }
}
