<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Exports;

use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Tenancy\OrganizationScope;

/**
 * Exports are snapshots of business data: they are deleted, file and record,
 * once expired; failed and stuck requests after a day.
 */
final readonly class PruneExports
{
    public function __construct(private Filesystem $filesystem) {}

    public function handle(): int
    {
        $deleted = 0;

        Export::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where(fn ($query) => $query
                ->where('expires_at', '<', now())
                ->orWhere(fn ($stale) => $stale->where('status', '!=', ExportStatus::Ready)->where('created_at', '<', now()->subDay())))
            ->orderBy('id')
            ->each(function (Export $export) use (&$deleted): void {
                if ($export->disk !== null && $export->path !== null) {
                    $this->filesystem->disk($export->disk)->delete($export->path);
                }

                $export->delete();
                $deleted++;
            });

        return $deleted;
    }
}
