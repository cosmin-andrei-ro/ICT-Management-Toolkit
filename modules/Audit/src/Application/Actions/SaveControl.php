<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\Audit\Domain\Enums\ControlStatus;
use Modules\Audit\Domain\Models\Control;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Registers a control (numbered C-001… per organization) or changes it.
 * Audited by the control's own trail.
 */
final readonly class SaveControl
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
    ) {}

    /**
     * @param  array{name: string, description: ?string, owner_id: ?string, status: ControlStatus, implementation_notes: ?string, test_interval_months: ?int}  $data  Validated.
     */
    public function handle(?Control $control, array $data): Control
    {
        return $this->db->transaction(function () use ($control, $data): Control {
            if ($control === null) {
                $organizationId = $this->context->requireId();
                // Serializes numbering within the organization.
                $this->db->table('core_organizations')->where('id', $organizationId)->lockForUpdate()->first();
                $max = Control::query()->withTrashed()->max('number');
                $control = new Control;
                $control->forceFill(['organization_id' => $organizationId, 'number' => (is_numeric($max) ? (int) $max : 0) + 1]);
            }

            $control->forceFill([
                'name' => trim($data['name']),
                'description' => self::text($data['description']),
                'owner_id' => $data['owner_id'],
                'status' => $data['status'],
                'implementation_notes' => self::text($data['implementation_notes']),
            ]);

            if ($control->test_interval_months !== $data['test_interval_months']) {
                // A new interval reschedules from the last test, or from today for a control never tested.
                $from = $control->last_tested_on ?? CarbonImmutable::today();
                $control->forceFill([
                    'test_interval_months' => $data['test_interval_months'],
                    'next_test_on' => $data['test_interval_months'] !== null ? $from->addMonthsNoOverflow($data['test_interval_months']) : null,
                ]);
            }

            $control->save();

            return $control;
        });
    }

    /**
     * What the control's owner keeps up to date.
     */
    public function implementation(Control $control, ControlStatus $status, ?string $notes): void
    {
        $control->forceFill(['status' => $status, 'implementation_notes' => self::text($notes)])->save();
    }

    private static function text(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : '';

        return $value !== '' ? $value : null;
    }
}
