<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Links;

use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\ProcessSummary;

/**
 * The BIA side of Core's BusinessProcesses contract: the objectives in force,
 * as copied onto each process at approval.
 */
final class BiaBusinessProcesses implements BusinessProcesses
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function search(string $terms, int $limit = 10): array
    {
        $terms = trim($terms);

        if (mb_strlen($terms) < 2) {
            return [];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_substr($terms, 0, 200)).'%';

        return array_values(BusinessProcess::query()->where('status', 'active')->whereLike('name', $like)->orderBy('name')->limit($limit)->get()
            ->map(fn (BusinessProcess $process): ProcessSummary => self::summary($process))
            ->all());
    }

    public function find(array $ids): array
    {
        $found = [];

        foreach (BusinessProcess::query()->whereKey($ids)->get() as $process) {
            $found[$process->id] = self::summary($process);
        }

        return $found;
    }

    public function assessed(): array
    {
        return array_values(BusinessProcess::query()->where('status', 'active')->whereNotNull('rto_minutes')->orderBy('rto_minutes')->orderBy('name')->get()
            ->map(fn (BusinessProcess $process): ProcessSummary => self::summary($process))
            ->all());
    }

    private static function summary(BusinessProcess $process): ProcessSummary
    {
        return new ProcessSummary($process->id, $process->name, route('bia.processes.show', $process), $process->rto_minutes, $process->rpo_minutes, $process->tier?->value);
    }
}
