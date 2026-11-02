<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Audit\Domain\Enums\AuditKind;
use Modules\Audit\Domain\Enums\AuditStatus;
use Modules\Audit\Domain\Models\Audit;
use Modules\Audit\Domain\Models\Framework;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Plans an audit (numbered AUD-001… per organization) or changes it, and
 * moves it through planned, in progress, completed or cancelled.
 */
final readonly class SaveAudit
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private AuditTrail $trail,
    ) {}

    /**
     * @param  array{title: string, kind: AuditKind, scope: ?string, lead_auditor_id: ?string, audit_body: ?string, starts_on: CarbonImmutable, ends_on: ?CarbonImmutable}  $data  Validated.
     * @param  list<string>  $frameworkIds
     */
    public function handle(?Audit $audit, array $data, array $frameworkIds, User $actor): Audit
    {
        return $this->db->transaction(function () use ($audit, $data, $frameworkIds, $actor): Audit {
            if ($audit === null) {
                $organizationId = $this->context->requireId();
                // Serializes numbering within the organization.
                $this->db->table('core_organizations')->where('id', $organizationId)->lockForUpdate()->first();
                $max = Audit::query()->withTrashed()->max('number');
                $audit = new Audit;
                $audit->forceFill(['organization_id' => $organizationId, 'number' => (is_numeric($max) ? (int) $max : 0) + 1]);
            }

            $audit->forceFill([
                'title' => trim($data['title']),
                'kind' => $data['kind'],
                'scope' => self::text($data['scope']),
                'lead_auditor_id' => $data['lead_auditor_id'],
                'audit_body' => self::text($data['audit_body']),
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
            ])->save();

            // Scoped lookup: frameworks of another organization are left out.
            $frameworks = Framework::query()->whereKey($frameworkIds)->pluck('id')->all();
            $before = $audit->frameworks()->pluck('audit_frameworks.id')->sort()->values()->all();
            $audit->frameworks()->sync(array_fill_keys(array_filter($frameworks, is_string(...)), ['organization_id' => $audit->organization_id]));
            $after = $audit->frameworks()->pluck('audit_frameworks.id')->sort()->values()->all();

            if ($before !== $after) {
                $this->trail->record(AuditEvent::Updated, $audit, ['frameworks' => $before], ['frameworks' => $after], $actor->id);
            }

            return $audit;
        });
    }

    /**
     * @throws InvalidArgumentException for a change the audit's status does not allow, or completing without a conclusion
     */
    public function changeStatus(Audit $audit, AuditStatus $status, ?string $conclusion): void
    {
        $allowed = match ($audit->status) {
            AuditStatus::Planned => [AuditStatus::InProgress, AuditStatus::Cancelled],
            AuditStatus::InProgress => [AuditStatus::Completed, AuditStatus::Cancelled],
            // A completed or cancelled audit is reopened to add what was missed.
            AuditStatus::Completed, AuditStatus::Cancelled => [AuditStatus::InProgress],
        };
        $conclusion = self::text($conclusion);

        if (! in_array($status, $allowed, true) || ($status === AuditStatus::Completed && $conclusion === null)) {
            throw new InvalidArgumentException('Not allowed.');
        }

        $audit->forceFill([
            'status' => $status,
            'completed_at' => $status === AuditStatus::Completed ? now() : null,
            'conclusion' => $status === AuditStatus::Completed ? $conclusion : $audit->conclusion,
        ])->save();
    }

    private static function text(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : '';

        return $value !== '' ? $value : null;
    }
}
