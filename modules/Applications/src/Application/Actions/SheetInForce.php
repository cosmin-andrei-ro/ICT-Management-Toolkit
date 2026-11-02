<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Actions;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;

/**
 * Brings a version into force, inside the caller's transaction: the previous
 * one is superseded, the version is numbered (1.0, then 1.1 for a minor
 * change or 2.0 for a major one), and the application's summary follows it.
 */
final readonly class SheetInForce
{
    public function __construct(private Config $config) {}

    public function handle(Sheet $sheet): void
    {
        $previous = Sheet::query()->where('application_id', $sheet->application_id)->where('status', SheetStatus::Approved)->lockForUpdate()->first();

        if ($previous !== null) {
            $previous->forceFill(['status' => SheetStatus::Superseded])->save();
        }

        [$major, $minor] = match (true) {
            $previous === null => [1, 0],
            $sheet->is_major => [(int) $previous->major + 1, 0],
            default => [(int) $previous->major, (int) $previous->minor + 1],
        };

        $sheet->forceFill(['status' => SheetStatus::Approved, 'major' => $major, 'minor' => $minor, 'approved_at' => now()])->save();

        $months = $this->config->get('applications.sheets.review_months');
        $classification = $sheet->section('1_3');

        $application = Application::query()->findOrFail($sheet->application_id);
        $application->forceFill([
            'status' => is_string($classification['status'] ?? null) ? $classification['status'] : null,
            'criticality' => is_string($classification['criticality'] ?? null) ? $classification['criticality'] : null,
            'current_version' => $sheet->versionLabel(),
            'approved_at' => $sheet->approved_at,
            'next_review_on' => now()->addMonthsNoOverflow(is_int($months) && $months > 0 ? $months : 12)->toDateString(),
        ])->save();
    }
}
