<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Exports;

use Generator;
use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Models\Export;
use Modules\Risk\Application\RiskAssets;
use Modules\Risk\Domain\Models\Acceptance;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskAction;
use Modules\Risk\Domain\Models\RiskSettings;

/**
 * The risk register for auditors (ISO/IEC 27001 §8.2–8.3 evidence): risks with
 * inherent and residual scores, treatment and acceptance; the treatment
 * actions; the acceptances and their decisions.
 */
final readonly class RiskRegisterExport implements ExportSource
{
    public const string TYPE = 'risk.register';

    public function __construct(private RiskAssets $assets) {}

    public function sheets(Export $export): array
    {
        return [
            new ExportSheet(__('risk::report.export.risks'), [
                __('risk::report.export.reference'), __('risk::risks.fields.title'), __('risk::risks.fields.category'), __('risk::risks.fields.owner'), __('risk::risks.fields.status'),
                __('risk::report.export.concerns'), __('risk::risks.fields.likelihood'), __('risk::risks.fields.impact'), __('risk::risks.fields.inherent'), __('risk::report.export.inherent_level'),
                __('risk::treatment.fields.option'), __('risk::treatment.fields.residual_likelihood'), __('risk::treatment.fields.residual_impact'), __('risk::treatment.fields.residual'), __('risk::report.export.residual_level'),
                __('risk::report.export.accepted_until'), __('risk::risks.fields.next_review'), __('risk::report.export.threats_vulnerabilities'), __('risk::risks.fields.existing_controls'),
            ], $this->riskRows()),
            new ExportSheet(__('risk::report.export.actions'), [__('risk::report.export.reference'), __('risk::treatment.fields.action'), __('risk::treatment.fields.action_owner'), __('risk::treatment.fields.due_on'), __('risk::risks.fields.status'), __('risk::report.export.overdue'), __('risk::report.export.ticket')], $this->actionRows()),
            new ExportSheet(__('risk::report.export.acceptances'), [__('risk::report.export.reference'), __('risk::report.export.score'), __('risk::risks.fields.status'), __('risk::report.export.requested_by'), __('risk::report.export.requested_at'), __('risk::report.export.decided_by'), __('risk::report.export.decided_at'), __('risk::report.export.valid_until'), __('risk::treatment.fields.justification'), __('risk::report.export.comment')], $this->acceptanceRows()),
        ];
    }

    public function filename(Export $export): string
    {
        return 'risk-register-'.$export->created_at?->format('Y-m-d');
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function riskRows(): Generator
    {
        $settings = RiskSettings::current();

        foreach (Risk::query()->with(['owner', 'catalogEntries'])->orderBy('reference')->lazy(200) as $risk) {
            $inherent = $risk->inherentScore();
            $residual = $risk->residualScore();

            yield [
                $risk->code(),
                $risk->title,
                $risk->category->label(),
                $risk->owner?->name,
                $risk->status->label(),
                implode('; ', array_map(fn ($asset): string => $asset->type->label().': '.$asset->name, $this->assets->resolve($risk->assets()))),
                $risk->inherent_likelihood,
                $risk->inherent_impact,
                $inherent,
                $inherent !== null ? $settings->level($inherent)->label() : null,
                $risk->treatment?->label(),
                $risk->residual_likelihood,
                $risk->residual_impact,
                $residual,
                $residual !== null ? $settings->level($residual)->label() : null,
                $risk->isAccepted() ? $risk->accepted_until?->toDateString() : null,
                $risk->next_review_on?->toDateString(),
                $risk->catalogEntries->pluck('name')->implode(', '),
                $risk->existing_controls,
            ];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function actionRows(): Generator
    {
        $risks = Risk::query()->withTrashed()->get(['id', 'reference'])->keyBy('id');

        foreach (RiskAction::query()->with('owner')->orderBy('due_on')->lazy(200) as $action) {
            yield [$risks->get($action->risk_id)?->code(), $action->title, $action->owner?->name, $action->due_on?->toDateString(), $action->status->label(), $action->isOverdue(), $action->ticket_number];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function acceptanceRows(): Generator
    {
        $risks = Risk::query()->withTrashed()->get(['id', 'reference'])->keyBy('id');

        foreach (Acceptance::query()->with(['requester', 'decider'])->orderBy('requested_at')->lazy(200) as $acceptance) {
            yield [
                $risks->get($acceptance->risk_id)?->code(), $acceptance->score(), $acceptance->status->label(),
                $acceptance->requester?->name, $acceptance->requested_at->toDateString(), $acceptance->decider?->name, $acceptance->decided_at?->toDateString(),
                $acceptance->valid_until?->toDateString(), $acceptance->justification, $acceptance->comment,
            ];
        }
    }
}
