<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Applications\Application\Notifications\SheetUpdate;
use Modules\Applications\Application\Sheets\SheetApprovers;
use Modules\Applications\Application\Sheets\SheetForm;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Exceptions\SheetLocked;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Domain\Sheet\Section;
use Modules\Core\Domain\Models\User;

/**
 * A complete version goes for approval when it is major (always the first),
 * or comes into force at once when it is minor (owner's decision: minor
 * changes are logged in the change history only).
 */
final readonly class SubmitSheet
{
    public function __construct(
        private ConnectionInterface $db,
        private SheetForm $form,
        private SheetInForce $inForce,
        private SheetApprovers $approvers,
    ) {}

    /**
     * @return bool True when the version came into force, false when it waits for approval.
     *
     * @throws SheetLocked unless draft or returned
     * @throws ValidationException listing incomplete sections (key "sections"), or without a change summary (key "changeSummary")
     */
    public function handle(Sheet $sheet, User $actor): bool
    {
        $inForce = $this->db->transaction(function () use ($sheet, $actor): bool {
            $locked = Sheet::query()->with('application')->lockForUpdate()->findOrFail($sheet->id);

            if (! $locked->status->isEditable()) {
                throw new SheetLocked;
            }

            $incomplete = $this->form->incomplete($locked);

            if ($incomplete !== []) {
                throw ValidationException::withMessages(['sections' => __('applications::sheet.errors.incomplete', [
                    'sections' => implode(', ', array_map(fn (Section $section): string => trim($section->number().' '.$section->title()), $incomplete)),
                ])]);
            }

            if ($locked->change_summary === null) {
                throw ValidationException::withMessages(['changeSummary' => __('applications::sheet.errors.change_summary')]);
            }

            $locked->forceFill(['submitted_at' => now(), 'submitted_by' => $actor->id, 'returned_at' => null, 'returned_by' => null, 'return_comment' => null]);

            if (! $locked->is_major) {
                $this->inForce->handle($locked, $actor);

                return true;
            }

            $locked->forceFill(['status' => SheetStatus::Submitted])->save();
            $locked->approvals()->delete();

            return false;
        });

        $sheet->refresh();

        if (! $inForce) {
            $notification = new SheetUpdate('submitted', $sheet->application->name, route('applications.sheets.show', $sheet));

            foreach ($this->approvers->candidates($sheet) as $user) {
                $user->notify($notification);
            }
        }

        return $inForce;
    }
}
