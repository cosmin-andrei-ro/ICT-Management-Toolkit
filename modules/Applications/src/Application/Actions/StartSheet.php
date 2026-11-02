<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\User;

/**
 * Opens the version being written: the open one if any, otherwise a copy of
 * the version in force. The first version always needs the approvals.
 */
final readonly class StartSheet
{
    public function __construct(
        private ConnectionInterface $db,
        private ConfigurationItems $items,
    ) {}

    public function handle(Application $application, User $actor): Sheet
    {
        return $this->db->transaction(function () use ($application, $actor): Sheet {
            Application::query()->lockForUpdate()->findOrFail($application->id);

            $open = Sheet::query()->where('application_id', $application->id)->whereIn('status', ['draft', 'submitted', 'returned'])->first();

            if ($open !== null) {
                return $open;
            }

            $current = Sheet::query()->where('application_id', $application->id)->where('status', SheetStatus::Approved)->first();

            $sheet = new Sheet;
            $sheet->forceFill([
                'organization_id' => $application->organization_id,
                'application_id' => $application->id,
                'is_major' => $current === null,
                'content' => $current !== null ? $current->content : ['doc' => ['document_owner' => $application->technical_owner_id ?? $actor->id]],
            ])->save();

            // The components linked in the CMDB (5.2) carry over too.
            foreach ($current !== null ? ($this->items->linkedIds($current->getMorphClass(), [$current->id])[$current->id] ?? []) : [] as $itemId) {
                $this->items->link($sheet, $itemId, $actor);
            }

            return $sheet;
        });
    }
}
