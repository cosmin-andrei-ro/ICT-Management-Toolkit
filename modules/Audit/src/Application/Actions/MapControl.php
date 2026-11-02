<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Actions;

use InvalidArgumentException;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;

/**
 * Says which requirements a control helps to meet, in any framework. Audited
 * on the control.
 */
final readonly class MapControl
{
    public function __construct(private AuditTrail $trail) {}

    /**
     * @throws InvalidArgumentException for a requirement of another organization
     */
    public function add(Control $control, string $requirementId, User $actor): void
    {
        // Scoped lookup: a requirement of another organization is not found.
        $requirement = Requirement::query()->with('framework')->find($requirementId) ?? throw new InvalidArgumentException('Unknown requirement.');
        $control->requirements()->syncWithoutDetaching([$requirement->id => ['organization_id' => $control->organization_id]]);
        $this->trail->record(AuditEvent::Updated, $control, null, ['requirement_added' => $requirement->framework->name.' '.$requirement->reference], $actor->id);
    }

    public function remove(Control $control, string $requirementId, User $actor): void
    {
        $requirement = Requirement::query()->with('framework')->find($requirementId);

        if ($requirement !== null) {
            $control->requirements()->detach($requirement->id);
            $this->trail->record(AuditEvent::Updated, $control, ['requirement_removed' => $requirement->framework->name.' '.$requirement->reference], null, $actor->id);
        }
    }
}
