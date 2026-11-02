<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Exports;

use Carbon\CarbonImmutable;
use Generator;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\ControlTest;
use Modules\Audit\Domain\Models\Evidence;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\FindingAction;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Models\Export;

/**
 * Compliance for auditors: the applicability of every requirement (the
 * Statement of Applicability for ISO/IEC 27001) with its controls, the
 * controls, their tests and evidence, the findings and their corrective
 * actions.
 */
final readonly class ComplianceExport implements ExportSource
{
    public const string TYPE = 'audit.compliance';

    public function sheets(Export $export): array
    {
        return [
            new ExportSheet(__('audit::report.export.applicability'), [
                __('audit::compliance.fields.framework'), __('audit::compliance.fields.group'), __('audit::compliance.fields.reference'), __('audit::compliance.fields.requirement'),
                __('audit::report.included'), __('audit::compliance.fields.justification'), __('audit::compliance.fields.controls'), __('audit::compliance.fields.state'),
            ], $this->requirementRows()),
            new ExportSheet(__('audit::compliance.controls.title'), [
                __('audit::compliance.controls.fields.control'), __('audit::compliance.controls.fields.name'), __('audit::compliance.controls.fields.owner'), __('audit::compliance.controls.fields.status'),
                __('audit::compliance.controls.fields.notes'), __('audit::compliance.controls.fields.requirements'), __('audit::evidence.tests.interval'), __('audit::report.export.last_tested'),
                __('audit::evidence.tests.next_column'), __('audit::report.export.test_overdue'), __('audit::evidence.title'),
            ], $this->controlRows()),
            new ExportSheet(__('audit::evidence.tests.title'), [
                __('audit::compliance.controls.fields.control'), __('audit::evidence.tests.tested_on'), __('audit::report.export.tester'), __('audit::evidence.tests.result'), __('audit::evidence.tests.method'), __('audit::evidence.tests.notes'),
            ], $this->testRows()),
            new ExportSheet(__('audit::evidence.title'), [
                __('audit::compliance.controls.fields.control'), __('audit::report.export.evidence'), __('audit::report.export.kind'), __('audit::evidence.note'), __('audit::evidence.valid_until_field'), __('audit::report.export.added_on'),
            ], $this->evidenceRows()),
            new ExportSheet(__('audit::audits.findings.title'), [
                __('audit::audits.findings.fields.finding'), __('audit::audits.fields.audit'), __('audit::audits.findings.fields.title'), __('audit::audits.findings.fields.severity'), __('audit::audits.findings.fields.control'),
                __('audit::audits.findings.fields.requirement'), __('audit::audits.findings.fields.owner'), __('audit::audits.findings.fields.due_on'), __('audit::audits.findings.fields.status'),
                __('audit::audits.findings.fields.root_cause'), __('audit::report.export.verified_by'), __('audit::report.export.closed_on'),
            ], $this->findingRows()),
            new ExportSheet(__('audit::audits.findings.actions'), [
                __('audit::audits.findings.fields.finding'), __('audit::audits.findings.action_title'), __('audit::audits.findings.fields.owner'), __('audit::audits.findings.fields.due_on'), __('audit::audits.findings.fields.status'), __('audit::report.export.ticket'),
            ], $this->actionRows()),
        ];
    }

    public function filename(Export $export): string
    {
        return 'compliance-'.$export->created_at?->format('Y-m-d');
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function requirementRows(): Generator
    {
        $frameworks = Framework::query()->get(['id', 'name'])->keyBy('id');

        foreach (Requirement::query()->with('controls')->orderBy('framework_id')->orderBy('position')->lazy(200) as $requirement) {
            yield [
                $frameworks->get($requirement->framework_id)?->name,
                $requirement->group,
                $requirement->reference,
                $requirement->title,
                $requirement->is_applicable,
                $requirement->justification,
                $requirement->controls->map(fn (Control $control): string => $control->code().' '.$control->name)->implode('; '),
                $requirement->state()->label(),
            ];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function controlRows(): Generator
    {
        $today = CarbonImmutable::today();

        foreach (Control::query()->with('owner')->withCount(['requirements', 'evidence'])->orderBy('number')->lazy(200) as $control) {
            yield [
                $control->code(), $control->name, $control->owner?->name, $control->status->label(), $control->implementation_notes,
                self::number($control->getAttribute('requirements_count')), $control->test_interval_months, $control->last_tested_on?->toDateString(),
                $control->next_test_on?->toDateString(), $control->isTestOverdue($today), self::number($control->getAttribute('evidence_count')),
            ];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function testRows(): Generator
    {
        $controls = Control::query()->withTrashed()->get(['id', 'number'])->keyBy('id');

        foreach (ControlTest::query()->with('tester')->orderBy('tested_on')->lazy(200) as $test) {
            yield [$controls->get($test->control_id)?->code(), $test->tested_on->toDateString(), $test->tester?->name, $test->result->label(), $test->method, $test->notes];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function evidenceRows(): Generator
    {
        $controls = Control::query()->withTrashed()->get(['id', 'number'])->keyBy('id');

        foreach (Evidence::query()->orderBy('control_id')->orderBy('created_at')->lazy(200) as $evidence) {
            yield [
                $controls->get($evidence->control_id)?->code(), $evidence->title, __('audit::report.export.kinds.'.$evidence->kind->value),
                $evidence->description, $evidence->valid_until?->toDateString(), $evidence->created_at?->toDateString(),
            ];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function findingRows(): Generator
    {
        foreach (Finding::query()->with(['audit', 'control', 'requirement.framework', 'owner', 'verifier'])->orderBy('number')->lazy(200) as $finding) {
            yield [
                $finding->code(), $finding->audit?->code(), $finding->title, $finding->severity->label(),
                $finding->control !== null ? $finding->control->code().' '.$finding->control->name : null,
                $finding->requirement !== null ? $finding->requirement->framework->name.' '.$finding->requirement->reference : null,
                $finding->owner?->name, $finding->due_on?->toDateString(), $finding->status->label(),
                $finding->root_cause, $finding->verifier?->name, $finding->closed_at?->toDateString(),
            ];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function actionRows(): Generator
    {
        $findings = Finding::query()->get(['id', 'number'])->keyBy('id');

        foreach (FindingAction::query()->with('owner')->orderBy('due_on')->lazy(200) as $action) {
            yield [$findings->get($action->finding_id)?->code(), $action->title, $action->owner?->name, $action->due_on?->toDateString(), $action->status->label(), $action->ticket_number];
        }
    }

    private static function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
