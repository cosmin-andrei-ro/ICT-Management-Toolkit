<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Livewire;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Bcm\Application\Exercises\CancelExercise;
use Modules\Bcm\Application\Exercises\CompleteExercise;
use Modules\Bcm\Application\Exercises\ExerciseClosed;
use Modules\Bcm\Application\Exercises\ManageFindings;
use Modules\Bcm\Application\Exercises\RecordExerciseResults;
use Modules\Bcm\Application\Exercises\StartExercise;
use Modules\Bcm\Application\PlanTiming;
use Modules\Bcm\Domain\Enums\ExerciseOutcome;
use Modules\Bcm\Domain\Enums\ExerciseStatus;
use Modules\Bcm\Domain\Enums\FindingSeverity;
use Modules\Bcm\Domain\Enums\StepResult;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\ExerciseItem;
use Modules\Bcm\Domain\Models\ExerciseStep;
use Modules\Bcm\Domain\Models\Finding;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\ProblemTickets;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Time\Duration;

/**
 * One exercise: started on the plan in force, its results recorded step by
 * step, findings raised as problems, then completed as evidence.
 *
 * @property-read Exercise $exercise Computed.
 */
#[Layout('core::components.layouts.app')]
final class ExercisePage extends Component
{
    #[Locked]
    public string $exerciseId = '';

    /** @var array<string, array{result: string, value: string, unit: string, notes: string}> By exercise step id. */
    public array $stepResults = [];

    /** @var array<string, array{recovered: string, value: string, unit: string, notes: string}> By exercise item id; recovered is yes, no or empty. */
    public array $itemResults = [];

    public string $findingDescription = '';

    public string $findingSeverity = 'medium';

    public string $findingStepId = '';

    public string $outcome = '';

    public string $summary = '';

    public string $lessons = '';

    public function mount(Exercise $exercise): void
    {
        $this->authorize('view', $exercise);
        $this->exerciseId = $exercise->id;
        $this->loadResults();
    }

    #[Computed]
    public function exercise(): Exercise
    {
        return Exercise::query()->with(['plan', 'version', 'facilitator'])->findOrFail($this->exerciseId);
    }

    public function start(StartExercise $start): void
    {
        $this->authorize('update', $this->exercise);
        $this->attempt(fn () => $start->handle($this->exercise), 'steps');
        $this->loadResults();
    }

    public function saveResults(RecordExerciseResults $record): void
    {
        $this->authorize('update', $this->exercise);
        $this->validateResults();
        $this->attempt(fn () => $record->handle($this->exercise, $this->stepPayload(), $this->itemPayload(), $this->actor()), 'steps');
    }

    public function complete(RecordExerciseResults $record, CompleteExercise $complete): void
    {
        $this->authorize('update', $this->exercise);
        $this->validateResults();
        $this->validate([
            'outcome' => ['required', Rule::enum(ExerciseOutcome::class)],
            'summary' => ['nullable', 'string', 'max:10000'],
            'lessons' => ['nullable', 'string', 'max:10000'],
        ]);

        $this->attempt(function () use ($record, $complete): void {
            $record->handle($this->exercise, $this->stepPayload(), $this->itemPayload(), $this->actor());
            $complete->handle($this->exercise, ExerciseOutcome::from($this->outcome), $this->summary, $this->lessons, $this->actor());
        }, 'outcome');
    }

    public function cancelExercise(CancelExercise $cancel): void
    {
        $this->authorize('update', $this->exercise);
        $this->attempt(fn () => $cancel->handle($this->exercise), 'outcome');
    }

    public function addFinding(ManageFindings $findings): void
    {
        $this->authorize('update', $this->exercise);
        $this->validate([
            'findingDescription' => ['required', 'string', 'max:5000'],
            'findingSeverity' => ['required', Rule::enum(FindingSeverity::class)],
            'findingStepId' => ['nullable', 'uuid'],
        ]);

        $added = $this->attempt(fn () => $findings->add($this->exercise, $this->findingDescription, FindingSeverity::from($this->findingSeverity), $this->findingStepId, $this->actor()), 'findingDescription');

        if ($added) {
            $this->reset('findingDescription', 'findingSeverity', 'findingStepId');
        }
    }

    public function removeFinding(string $findingId, ManageFindings $findings): void
    {
        $this->authorize('update', $this->exercise);
        $finding = Finding::query()->where('exercise_id', $this->exerciseId)->findOrFail($findingId);
        $this->attempt(fn () => $findings->remove($finding, $this->actor()), 'findings');
    }

    public function raiseTicket(string $findingId, ManageFindings $findings): void
    {
        $this->authorize('raiseTicket', $this->exercise);
        $finding = Finding::query()->where('exercise_id', $this->exerciseId)->findOrFail($findingId);
        $this->attempt(fn () => $findings->raiseTicket($finding, $this->actor()), 'findings');
    }

    public function render(ConfigurationItems $items, ProblemTickets $tickets, PlanTiming $timing): View
    {
        $exercise = $this->exercise;
        $steps = $exercise->steps()->get();
        $exerciseItems = $exercise->items()->get();
        $findings = $exercise->findings()->with(['step', 'author'])->get();
        $itemIds = [...$exerciseItems->map(fn (ExerciseItem $item): string => $item->configuration_item_id), ...$steps->map(fn (ExerciseStep $step): ?string => $step->configuration_item_id)->filter()];
        $profiles = $items->recoveryProfiles(array_values(array_unique($itemIds)));

        return view('bcm::livewire.exercises.show', [
            'exercise' => $exercise,
            'plan' => $exercise->plan,
            'editable' => auth()->user()?->can('update', $exercise) === true,
            'canRaise' => $tickets->isAvailable() && auth()->user()?->can('raiseTicket', $exercise) === true,
            'steps' => $steps,
            'exerciseItems' => $exerciseItems,
            'profiles' => $profiles,
            'findings' => $findings,
            'tickets' => $tickets->find(array_values(array_filter($findings->pluck('ticket_id')->all(), is_string(...)))),
            'measured' => $exercise->status === ExerciseStatus::Completed ? $exercise->measured_minutes : $exercise->measuredRecoveryMinutes(),
            'estimate' => $exercise->version?->estimatedRecoveryMinutes(),
            // Kept as it was on completion; the current requirement until then.
            'rto' => $exercise->status === ExerciseStatus::Completed ? $exercise->rto_minutes : ($exercise->version !== null ? $timing->strictestRto($exercise->version)?->rtoMinutes : null),
            'results' => StepResult::cases(),
            'outcomes' => ExerciseOutcome::cases(),
            'severities' => FindingSeverity::cases(),
            'units' => array_keys(Duration::UNITS),
        ])->title($exercise->title);
    }

    private function loadResults(): void
    {
        $this->stepResults = [];
        $this->itemResults = [];

        foreach (ExerciseStep::query()->where('exercise_id', $this->exerciseId)->orderBy('position')->get() as $step) {
            ['value' => $value, 'unit' => $unit] = Duration::split($step->actual_minutes);
            $this->stepResults[$step->id] = ['result' => $step->result->value, 'value' => $value, 'unit' => $step->actual_minutes === null ? 'minutes' : $unit, 'notes' => $step->notes ?? ''];
        }

        foreach (ExerciseItem::query()->where('exercise_id', $this->exerciseId)->get() as $item) {
            ['value' => $value, 'unit' => $unit] = Duration::split($item->measured_minutes);
            $this->itemResults[$item->id] = ['recovered' => match ($item->recovered) {
                true => 'yes', false => 'no', null => ''
            }, 'value' => $value, 'unit' => $item->measured_minutes === null ? 'minutes' : $unit, 'notes' => $item->notes ?? ''];
        }
    }

    private function validateResults(): void
    {
        $units = array_keys(Duration::UNITS);

        $this->validate([
            'stepResults' => ['array'],
            'stepResults.*.result' => ['required', Rule::enum(StepResult::class)],
            'stepResults.*.value' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'stepResults.*.unit' => ['required', Rule::in($units)],
            'stepResults.*.notes' => ['nullable', 'string', 'max:2000'],
            'itemResults' => ['array'],
            'itemResults.*.recovered' => ['nullable', Rule::in(['', 'yes', 'no'])],
            'itemResults.*.value' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'itemResults.*.unit' => ['required', Rule::in($units)],
            'itemResults.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * @return array<string, array{result: string, actual_minutes: ?int, notes: ?string}>
     */
    private function stepPayload(): array
    {
        $payload = [];

        foreach ($this->stepResults as $id => $row) {
            $payload[(string) $id] = ['result' => $row['result'], 'actual_minutes' => Duration::toMinutes($row['value'], $row['unit']), 'notes' => $row['notes']];
        }

        return $payload;
    }

    /**
     * @return array<string, array{recovered: ?bool, measured_minutes: ?int, notes: ?string}>
     */
    private function itemPayload(): array
    {
        $payload = [];

        foreach ($this->itemResults as $id => $row) {
            $payload[(string) $id] = [
                'recovered' => match ($row['recovered']) {
                    'yes' => true, 'no' => false, default => null
                },
                'measured_minutes' => Duration::toMinutes($row['value'], $row['unit']),
                'notes' => $row['notes'],
            ];
        }

        return $payload;
    }

    private function attempt(Closure $change, string $errorKey): bool
    {
        try {
            $change();

            return true;
        } catch (ExerciseClosed) {
            $this->addError($errorKey, __('bcm::exercises.errors.closed'));
        } catch (InvalidArgumentException) {
            $this->addError($errorKey, __('bcm::exercises.errors.invalid'));
        } finally {
            unset($this->exercise);
        }

        return false;
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
