<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Bcm\Domain\Enums\Phase;
use Modules\Bcm\Domain\Exceptions\VersionLocked;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Bcm\Domain\Models\Step;
use Modules\Bcm\Domain\Models\TeamMember;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * What an open plan version covers and how it is carried out: BIA processes and
 * CMDB items in scope (through Core contracts), the crisis team and the
 * runbook. Every change is audited on the version.
 */
final readonly class ManagePlanContent
{
    public function __construct(
        private ConnectionInterface $db,
        private BusinessProcesses $processes,
        private ConfigurationItems $items,
        private AuditTrail $trail,
        private OrganizationContext $context,
    ) {}

    /**
     * @throws VersionLocked
     * @throws InvalidArgumentException for a process of another organization
     */
    public function addProcess(PlanVersion $version, string $processId, User $actor): void
    {
        $this->assertOpen($version);
        $process = Str::isUuid($processId) ? ($this->processes->find([$processId])[$processId] ?? null) : null;

        if ($process === null) {
            throw new InvalidArgumentException('Unknown business process.');
        }

        $this->db->table('bcm_version_processes')->insertOrIgnore(['version_id' => $version->id, 'organization_id' => $version->organization_id, 'process_id' => $process->id]);
        $this->trail->record(AuditEvent::Updated, $version, null, ['process_added' => $process->name], $actor->id);
    }

    public function removeProcess(PlanVersion $version, string $processId, User $actor): void
    {
        $this->assertOpen($version);
        $this->db->table('bcm_version_processes')->where('version_id', $version->id)->where('process_id', $processId)->delete();
        $name = $this->processes->find([$processId])[$processId]->name ?? $processId;
        $this->trail->record(AuditEvent::Updated, $version, ['process_removed' => $name], null, $actor->id);
    }

    /**
     * @throws VersionLocked
     * @throws InvalidArgumentException for an item of another organization
     */
    public function linkItem(PlanVersion $version, string $itemId, User $actor): void
    {
        $this->assertOpen($version);
        $this->items->link($version, $itemId, $actor);
    }

    public function unlinkItem(PlanVersion $version, string $itemId, User $actor): void
    {
        $this->assertOpen($version);
        $this->items->unlink($version, $itemId, $actor);
    }

    /**
     * @param  array{role: string, user_id?: ?string, contact_name?: ?string, contact_phone?: ?string, contact_email?: ?string, is_deputy?: bool, responsibilities?: ?string}  $member
     *
     * @throws VersionLocked
     * @throws InvalidArgumentException without a role, or without a person or a contact name
     */
    public function addMember(PlanVersion $version, array $member, User $actor): TeamMember
    {
        $this->assertOpen($version);
        $role = trim($member['role']);
        $userId = self::blankToNull($member['user_id'] ?? null);
        $contactName = self::blankToNull($member['contact_name'] ?? null);

        if ($role === '' || ($userId === null && $contactName === null)) {
            throw new InvalidArgumentException('A team member needs a role and a person or a contact.');
        }

        if ($userId !== null && (! Str::isUuid($userId) || ! User::query()->whereKey($userId)->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($this->context->requireId()))->exists())) {
            throw new InvalidArgumentException('The person must be an active member of the organization.');
        }

        $position = TeamMember::query()->where('version_id', $version->id)->max('position');

        $teamMember = new TeamMember;
        $teamMember->forceFill([
            'organization_id' => $version->organization_id,
            'version_id' => $version->id,
            'position' => (is_numeric($position) ? (int) $position : 0) + 1,
            'role' => mb_substr($role, 0, 255),
            'user_id' => $userId,
            'contact_name' => $userId === null ? mb_substr((string) $contactName, 0, 255) : null,
            'contact_phone' => $userId === null ? self::blankToNull($member['contact_phone'] ?? null) : null,
            'contact_email' => $userId === null ? self::blankToNull($member['contact_email'] ?? null) : null,
            'is_deputy' => $member['is_deputy'] ?? false,
            'responsibilities' => self::blankToNull($member['responsibilities'] ?? null),
        ])->save();

        $this->trail->record(AuditEvent::Updated, $version, null, ['team_member_added' => $role.': '.$teamMember->displayName()], $actor->id);

        return $teamMember;
    }

    public function removeMember(TeamMember $member, User $actor): void
    {
        $version = PlanVersion::query()->findOrFail($member->version_id);
        $this->assertOpen($version);
        $label = $member->role.': '.$member->displayName();
        $member->delete();
        $this->trail->record(AuditEvent::Updated, $version, ['team_member_removed' => $label], null, $actor->id);
    }

    /**
     * @param  array{phase: string, action: string, responsible?: ?string, responsible_user_id?: ?string, estimated_minutes?: ?int, configuration_item_id?: ?string, notes?: ?string}  $step
     *
     * @throws VersionLocked
     * @throws InvalidArgumentException for an empty action, or a person or item outside the organization
     */
    public function saveStep(PlanVersion $version, ?Step $existing, array $step, User $actor): Step
    {
        $this->assertOpen($version);
        $phase = Phase::from($step['phase']);
        $action = trim($step['action']);
        $userId = self::blankToNull($step['responsible_user_id'] ?? null);
        $itemId = self::blankToNull($step['configuration_item_id'] ?? null);

        if ($action === '') {
            throw new InvalidArgumentException('A step needs an action.');
        }

        if ($userId !== null && (! Str::isUuid($userId) || ! User::query()->whereKey($userId)->whereHas('organizations', fn ($query) => $query->whereKey($this->context->requireId()))->exists())) {
            throw new InvalidArgumentException('The responsible person must belong to the organization.');
        }

        if ($itemId !== null && (! Str::isUuid($itemId) || $this->items->recoveryProfiles([$itemId]) === [])) {
            throw new InvalidArgumentException('The configuration item must belong to the organization.');
        }

        if ($existing !== null && $existing->version_id !== $version->id) {
            throw new InvalidArgumentException('The step belongs to another version.');
        }

        $position = Step::query()->where('version_id', $version->id)->max('position');
        $record = $existing ?? new Step;
        $record->forceFill([
            'organization_id' => $version->organization_id,
            'version_id' => $version->id,
            'position' => $existing->position ?? (is_numeric($position) ? (int) $position : 0) + 1,
            'phase' => $phase,
            'action' => $action,
            'responsible' => self::blankToNull($step['responsible'] ?? null),
            'responsible_user_id' => $userId,
            'estimated_minutes' => $step['estimated_minutes'] ?? null,
            'configuration_item_id' => $itemId,
            'notes' => self::blankToNull($step['notes'] ?? null),
        ])->save();

        $this->trail->record(AuditEvent::Updated, $version, null, [$existing !== null ? 'step_changed' : 'step_added' => $record->position.'. '.mb_substr($action, 0, 200)], $actor->id);

        return $record;
    }

    public function removeStep(Step $step, User $actor): void
    {
        $version = PlanVersion::query()->findOrFail($step->version_id);
        $this->assertOpen($version);
        $label = $step->position.'. '.mb_substr($step->action, 0, 200);

        $this->db->transaction(function () use ($step, $version): void {
            $step->delete();
            // Keep the numbering continuous.
            foreach (Step::query()->where('version_id', $version->id)->orderBy('position')->get() as $index => $remaining) {
                $remaining->forceFill(['position' => $index + 1])->save();
            }
        });

        $this->trail->record(AuditEvent::Updated, $version, ['step_removed' => $label], null, $actor->id);
    }

    /**
     * Swaps a step with its neighbour (-1 up, +1 down).
     */
    public function moveStep(Step $step, int $direction): void
    {
        $version = PlanVersion::query()->findOrFail($step->version_id);
        $this->assertOpen($version);
        $neighbour = Step::query()->where('version_id', $version->id)->where('position', $step->position + ($direction < 0 ? -1 : 1))->first();

        if ($neighbour === null) {
            return;
        }

        $this->db->transaction(function () use ($step, $neighbour): void {
            [$a, $b] = [$step->position, $neighbour->position];
            $step->forceFill(['position' => $b])->save();
            $neighbour->forceFill(['position' => $a])->save();
        });
    }

    private function assertOpen(PlanVersion $version): void
    {
        if (! $version->status->isEditable()) {
            throw new VersionLocked;
        }
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
