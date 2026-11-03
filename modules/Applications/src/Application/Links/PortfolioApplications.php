<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Links;

use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Models\Application;
use Modules\Core\Domain\Links\Applications;
use Modules\Core\Domain\Links\ApplicationSummary;

/**
 * The Applications side of Core's Applications contract.
 */
final class PortfolioApplications implements Applications
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function search(string $terms, int $limit = 10): array
    {
        $terms = trim(mb_substr($terms, 0, 200));

        if (mb_strlen($terms) < 2) {
            return [];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $terms).'%';

        return array_values(Application::query()
            ->where(fn ($query) => $query->whereLike('name', $like)->orWhereLike('code', $like))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Application $application): ApplicationSummary => self::summary($application))
            ->all());
    }

    public function find(array $ids): array
    {
        $found = [];

        foreach (Application::query()->whereKey($ids)->get() as $application) {
            $found[$application->id] = self::summary($application);
        }

        return $found;
    }

    private static function summary(Application $application): ApplicationSummary
    {
        $approved = $application->sheets()->where('status', SheetStatus::Approved->value)->latest('approved_at')->first();

        return new ApplicationSummary(
            $application->id,
            $application->code,
            $application->name,
            route('applications.show', $application),
            $application->criticality,
            $approved !== null,
            $application->configuration_item_id,
            $approved !== null ? array_values(array_filter((array) ($approved->section('10_1')['processes'] ?? []), is_string(...))) : [],
        );
    }
}
