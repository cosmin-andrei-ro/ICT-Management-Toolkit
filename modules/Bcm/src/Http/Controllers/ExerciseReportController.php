<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Bcm\Domain\Enums\ExerciseStatus;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\ExerciseItem;
use Modules\Bcm\Domain\Models\ExerciseStep;
use Modules\Core\Application\Pdf\PdfDownload;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\ProblemTickets;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * The report of a completed exercise or recovery test (ISO 22301 8.5): steps
 * and their results, systems recovered, measured recovery time against the
 * RTO, findings and lessons. Print it, or download it as PDF (recorded as an
 * export).
 */
final readonly class ExerciseReportController
{
    public function __construct(
        private Gate $gate,
        private ConfigurationItems $items,
        private ProblemTickets $tickets,
        private OrganizationContext $context,
        private PdfDownload $pdf,
    ) {}

    public function show(Exercise $exercise): View
    {
        $this->authorize($exercise);

        return view('bcm::exercise-report', [...$this->data($exercise), 'pdf' => false, 'pdfAvailable' => $this->pdf->isAvailable()]);
    }

    public function pdf(Exercise $exercise, Request $request): Response
    {
        $this->authorize($exercise);
        $html = view('bcm::exercise-report', [...$this->data($exercise), 'pdf' => true, 'pdfAvailable' => true, 'styles' => $this->pdf->stylesheet()])->render();
        $user = $request->user();

        return $this->pdf->respond($html, Str::slug($exercise->title).'-'.$exercise->scheduled_on->toDateString(), route('bcm.exercises.report', $exercise), $exercise, [], $user instanceof User ? $user : null);
    }

    private function authorize(Exercise $exercise): void
    {
        $this->gate->authorize('view', $exercise);
        // A report records what happened: only once the exercise is over.
        abort_unless($exercise->status === ExerciseStatus::Completed, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Exercise $exercise): array
    {
        $exercise->load(['plan', 'version', 'facilitator']);
        $steps = $exercise->steps()->get();
        $exerciseItems = $exercise->items()->get();
        $findings = $exercise->findings()->with(['step', 'author'])->get();
        $itemIds = [...$exerciseItems->map(fn (ExerciseItem $item): string => $item->configuration_item_id), ...$steps->map(fn (ExerciseStep $step): ?string => $step->configuration_item_id)->filter()];

        return [
            'exercise' => $exercise,
            'plan' => $exercise->plan,
            'organization' => Organization::query()->find($this->context->requireId()),
            'steps' => $steps,
            'exerciseItems' => $exerciseItems,
            'profiles' => $this->items->recoveryProfiles(array_values(array_unique($itemIds))),
            'findings' => $findings,
            'tickets' => $this->tickets->find(array_values(array_filter($findings->pluck('ticket_id')->all(), is_string(...)))),
            'estimate' => $exercise->version?->estimatedRecoveryMinutes(),
            'timezone' => config()->string('app.display_timezone'),
        ];
    }
}
