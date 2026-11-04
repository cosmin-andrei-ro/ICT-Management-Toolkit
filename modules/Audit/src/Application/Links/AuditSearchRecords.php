<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Links;

use Illuminate\Support\Facades\Gate;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Finding;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;

/**
 * Controls and findings for the global search, only for those who may view them.
 */
final class AuditSearchRecords
{
    /**
     * @return list<LinkedRecord>
     */
    public static function controls(string $term, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values(Control::query()->whereLike('name', LinkedRecords::like($term))->latest()->limit(20)->get()
            ->filter(fn (Control $control): bool => $gate->allows('view', $control))
            ->map(fn (Control $control): LinkedRecord => new LinkedRecord($control->id, $control->code().' '.$control->name, route('audit.controls.show', $control)))
            ->all());
    }

    /**
     * @return list<LinkedRecord>
     */
    public static function findings(string $term, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values(Finding::query()->whereLike('title', LinkedRecords::like($term))->latest()->limit(20)->get()
            ->filter(fn (Finding $finding): bool => $gate->allows('view', $finding))
            ->map(fn (Finding $finding): LinkedRecord => new LinkedRecord($finding->id, $finding->code().' '.$finding->title, route('audit.findings.show', $finding)))
            ->all());
    }
}
