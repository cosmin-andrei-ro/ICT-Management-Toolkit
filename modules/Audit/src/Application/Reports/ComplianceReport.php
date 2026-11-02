<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\Audit\Application\Frameworks\Coverage;
use Modules\Audit\Domain\Enums\ControlStatus;
use Modules\Audit\Domain\Enums\FindingSeverity;
use Modules\Audit\Domain\Enums\FindingStatus;
use Modules\Audit\Domain\Models\Audit;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Evidence;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The compliance report for management and auditors, and each framework's
 * Statement of Applicability.
 */
final readonly class ComplianceReport
{
    public function __construct(
        private Coverage $coverage,
        private OrganizationContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(CarbonImmutable $today): array
    {
        $frameworks = Framework::query()->where('is_active', true)->orderBy('name')->get();
        $controls = Control::query()->with('owner')->withCount('requirements')->orderBy('number')->get();
        $openFindings = Finding::query()->with(['owner', 'audit', 'control'])->where('status', '!=', FindingStatus::Closed->value)->get()
            ->sortBy(fn (Finding $finding): string => array_search($finding->severity, FindingSeverity::cases(), true).'-'.($finding->due_on?->toDateString() ?? '9999'))->values();

        return [
            'organization' => $this->organization(),
            'today' => $today,
            'frameworks' => $frameworks,
            'coverage' => $frameworks->mapWithKeys(fn (Framework $framework): array => [$framework->id => $this->coverage->of($framework)]),
            'controls' => $controls,
            'controlStatuses' => array_map(fn (ControlStatus $status): array => ['status' => $status, 'count' => $controls->where('status', $status)->count()], ControlStatus::cases()),
            'testsOverdue' => $controls->filter(fn (Control $control): bool => $control->isTestOverdue($today))->values(),
            'unmapped' => $controls->where('status', '!=', ControlStatus::Retired)->where('requirements_count', 0)->values(),
            'expiredEvidence' => Evidence::query()->whereNotNull('valid_until')->where('valid_until', '<', $today->toDateString())->count(),
            'audits' => Audit::query()->with('leadAuditor')->withCount('findings')->where('starts_on', '>=', $today->subYear()->toDateString())->orderByDesc('starts_on')->get(),
            'openFindings' => $openFindings,
            'findingsBySeverity' => array_map(fn (FindingSeverity $severity): array => ['severity' => $severity, 'count' => $openFindings->where('severity', $severity)->count()], FindingSeverity::cases()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statementOfApplicability(Framework $framework, CarbonImmutable $today): array
    {
        /** @var Collection<int, Requirement> $requirements */
        $requirements = Requirement::query()->with(['controls.owner'])->where('framework_id', $framework->id)->orderBy('position')->get();

        return [
            'organization' => $this->organization(),
            'today' => $today,
            'framework' => $framework,
            'coverage' => $this->coverage->of($framework),
            'groups' => $requirements->groupBy(fn (Requirement $requirement): string => $requirement->group ?? ''),
        ];
    }

    private function organization(): ?Organization
    {
        return $this->context->has() ? Organization::query()->find($this->context->id()) : null;
    }
}
