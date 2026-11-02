<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Actions;

use InvalidArgumentException;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Core\Domain\Models\User;

/**
 * Requirements of a framework: added to the organization's own frameworks,
 * worded (e.g. the licensed text of a standard), and declared applicable or
 * not with a justification. Audited by the requirement's own trail.
 */
final readonly class ManageRequirements
{
    /**
     * @throws InvalidArgumentException for an empty or duplicate reference or title
     */
    public function add(Framework $framework, string $reference, string $title, ?string $group, ?string $description): Requirement
    {
        $reference = trim($reference);
        $title = trim($title);

        if ($reference === '' || $title === '' || Requirement::query()->where('framework_id', $framework->id)->where('reference', $reference)->exists()) {
            throw new InvalidArgumentException('A requirement needs a new reference and a title.');
        }

        $position = Requirement::query()->where('framework_id', $framework->id)->max('position');
        $requirement = new Requirement;
        $requirement->forceFill([
            'organization_id' => $framework->organization_id,
            'framework_id' => $framework->id,
            'reference' => mb_substr($reference, 0, 64),
            'title' => mb_substr($title, 0, 255),
            'group' => $group !== null && trim($group) !== '' ? mb_substr(trim($group), 0, 255) : null,
            'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
            'position' => (is_numeric($position) ? (int) $position : -1) + 1,
        ])->save();

        return $requirement;
    }

    /**
     * @throws InvalidArgumentException when a requirement is declared not applicable without a reason
     */
    public function decide(Requirement $requirement, ?bool $applicable, ?string $justification, ?string $description, User $actor): void
    {
        $justification = $justification !== null ? trim($justification) : '';

        if ($applicable === false && $justification === '') {
            throw new InvalidArgumentException('Say why the requirement does not apply.');
        }

        $description = $description !== null ? trim($description) : '';

        $requirement->forceFill([
            'is_applicable' => $applicable,
            'justification' => $justification !== '' ? $justification : null,
            'description' => $description !== '' ? $description : null,
            'updated_by' => $actor->id,
        ])->save();
    }
}
