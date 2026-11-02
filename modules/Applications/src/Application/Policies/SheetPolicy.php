<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Policies;

use Modules\Applications\Application\Sheets\SheetApprovers;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Core\Domain\Models\User;

final readonly class SheetPolicy
{
    public function __construct(
        private ApplicationPolicy $applications,
        private SheetApprovers $approvers,
    ) {}

    public function view(User $user, Sheet $sheet): bool
    {
        return $this->applications->view($user, $sheet->application) || $this->approvers->rolesFor($user, $sheet) !== [];
    }

    public function update(User $user, Sheet $sheet): bool
    {
        return $sheet->status->isEditable() && $this->applications->edit($user, $sheet->application);
    }

    /**
     * Give one of the approvals still missing, or return the version.
     */
    public function decide(User $user, Sheet $sheet): bool
    {
        return $this->applications->view($user, $sheet->application) && $this->approvers->rolesFor($user, $sheet) !== [];
    }
}
