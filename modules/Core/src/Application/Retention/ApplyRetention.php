<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Retention;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\RetentionSettings;
use Modules\Core\Domain\Retention\DataRetention;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Applies every retention policy in every organization (ADR 0060), then
 * purges the audit entries that belong to no organization (sign-ins, changes
 * to people) after the longest audit period of all organizations. What each
 * run handled is itself recorded in the audit log.
 */
final readonly class ApplyRetention
{
    public const string AUDIT_POLICY = 'core.audit_log';

    public function __construct(
        private DataRetention $retention,
        private OrganizationContext $context,
        private AuditTrail $trail,
        private ConnectionInterface $db,
    ) {}

    /**
     * @return array<string, array<string, int>> Organization id (or "none") => policy => records handled.
     */
    public function handle(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $report = [];
        $auditYears = [];

        foreach (Organization::query()->orderBy('id')->pluck('id') as $organizationId) {
            if (! is_string($organizationId)) {
                continue;
            }

            $counts = $this->context->runAs($organizationId, function () use ($organizationId, $today, &$auditYears): array {
                $settings = RetentionSettings::current();
                $counts = [];

                foreach ($this->retention->policies() as $key => $policy) {
                    $years = $settings->years($key, $policy['default']);

                    if ($key === self::AUDIT_POLICY) {
                        $auditYears[] = $years;
                    }

                    if ($years !== null) {
                        $counts[$key] = ($policy['apply'])($organizationId, $today->subYears($years));
                    }
                }

                $handled = array_filter($counts);

                if ($handled !== []) {
                    $this->trail->record(AuditEvent::RetentionApplied, null, null, $handled);
                }

                return $handled;
            });

            if ($counts !== []) {
                $report[$organizationId] = $counts;
            }
        }

        // Entries of no organization are kept as long as the organization that keeps its own longest.
        $periods = array_values(array_filter($auditYears, is_int(...)));

        if ($periods !== [] && count($periods) === count($auditYears)) {
            $purged = $this->purgeAuditLog(null, $today->subYears(max($periods)));

            if ($purged > 0) {
                $report['none'] = [self::AUDIT_POLICY => $purged];
            }
        }

        return $report;
    }

    /**
     * Deletes the audit entries of the organization (or of none) older than the cut-off.
     */
    public function purgeAuditLog(?string $organizationId, CarbonImmutable $before): int
    {
        $result = $this->db->selectOne('SELECT core_purge_audit_log(?::uuid, ?::timestamptz) AS purged', [$organizationId, $before->toIso8601String()]);

        return is_object($result) && is_numeric($result->purged ?? null) ? (int) $result->purged : 0;
    }
}
