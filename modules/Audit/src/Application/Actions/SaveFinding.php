<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Audit\Application\Notifications\FindingUpdate;
use Modules\Audit\Domain\Enums\FindingSeverity;
use Modules\Audit\Domain\Models\Audit;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\ControlTest;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Raises a finding (numbered F-001… per organization) in an audit or from a
 * control test, or changes it; its owner is told when it is assigned to them.
 */
final readonly class SaveFinding
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
    ) {}

    /**
     * @param  array{title: string, description: string, severity: FindingSeverity, owner_id: ?string, due_on: ?CarbonImmutable, control_id: ?string, requirement_id: ?string}  $data  Validated.
     *
     * @throws InvalidArgumentException for a control, requirement or owner outside the organization
     */
    public function handle(?Finding $finding, array $data, User $actor, ?Audit $audit = null, ?ControlTest $test = null): Finding
    {
        // Scoped lookups: records of another organization are not found.
        if (($data['control_id'] !== null && ! Control::query()->whereKey($data['control_id'])->exists())
            || ($data['requirement_id'] !== null && ! Requirement::query()->whereKey($data['requirement_id'])->exists())
            || ($data['owner_id'] !== null && ! User::query()->whereKey($data['owner_id'])->whereHas('organizations', fn ($query) => $query->whereKey($this->context->requireId()))->exists())) {
            throw new InvalidArgumentException('Unknown control, requirement or owner.');
        }

        $previousOwner = $finding?->owner_id;

        $finding = $this->db->transaction(function () use ($finding, $data, $audit, $test): Finding {
            if ($finding === null) {
                $organizationId = $this->context->requireId();
                $this->db->table('core_organizations')->where('id', $organizationId)->lockForUpdate()->first();
                $max = Finding::query()->max('number');
                $finding = new Finding;
                $finding->forceFill([
                    'organization_id' => $organizationId,
                    'number' => (is_numeric($max) ? (int) $max : 0) + 1,
                    'audit_id' => $audit?->id,
                    'test_id' => $test?->id,
                ]);
            }

            $finding->forceFill([
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'severity' => $data['severity'],
                'owner_id' => $data['owner_id'],
                'due_on' => $data['due_on'],
                'control_id' => $data['control_id'],
                'requirement_id' => $data['requirement_id'],
            ])->save();

            return $finding;
        });

        if ($finding->owner_id !== null && $finding->owner_id !== $previousOwner && $finding->owner_id !== $actor->id) {
            $owner = User::query()->find($finding->owner_id);

            if ($owner !== null && $owner->canSignIn()) {
                $owner->notify(new FindingUpdate('assigned', $finding->code().' '.$finding->title, route('audit.findings.show', $finding), $finding->due_on?->toDateString()));
            }
        }

        return $finding;
    }
}
