<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Actions;

use Modules\Applications\Domain\Exceptions\SheetLocked;
use Modules\Applications\Domain\Models\Sheet;

/**
 * Stores sections of an open version, already normalized by SheetForm, with
 * whether it is a major change and what changed. Audited on the version by
 * its own trail.
 */
final readonly class SaveSheet
{
    /**
     * @param  array<string, array<string, mixed>>  $sections  By section key.
     *
     * @throws SheetLocked unless the version is draft or returned
     */
    public function handle(Sheet $sheet, array $sections, bool $isMajor, ?string $changeSummary): void
    {
        if (! $sheet->status->isEditable()) {
            throw new SheetLocked;
        }

        $isFirst = ! Sheet::query()->where('application_id', $sheet->application_id)->whereNotNull('major')->exists();
        $summary = $changeSummary !== null ? trim($changeSummary) : '';

        $sheet->forceFill([
            'content' => array_replace($sheet->content, $sections),
            // The first version is always approved.
            'is_major' => $isFirst || $isMajor,
            'change_summary' => $summary !== '' ? mb_substr($summary, 0, 2000) : null,
        ])->save();
    }
}
