<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Livewire;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Bia\Application\Actions\DecideAssessment;
use Modules\Bia\Application\Actions\SaveAssessment;
use Modules\Bia\Application\Actions\SubmitAssessment;
use Modules\Bia\Application\CurrentMatrix;
use Modules\Bia\Domain\Duration;
use Modules\Bia\Domain\Exceptions\AssessmentLocked;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BiaSettings;
use Modules\Bia\Domain\Models\ImpactCategory;
use Modules\Bia\Domain\Models\TimeHorizon;
use Modules\Core\Domain\Models\User;

/**
 * One version of a process's assessment: filled in by the owner while open,
 * read by everyone who may see the process, decided by an approver once
 * submitted.
 *
 * @property-read Assessment $assessment Computed.
 */
#[Layout('core::components.layouts.app')]
final class AssessmentPage extends Component
{
    #[Locked]
    public string $assessmentId = '';

    /** @var array<string, string> Keyed "categoryId|horizonId", "1"–"5" or "". */
    public array $scores = [];

    public bool $overrideMtpd = false;

    public string $mtpdValue = '';

    public string $mtpdUnit = 'hours';

    public string $mtpdReason = '';

    public string $rtoValue = '';

    public string $rtoUnit = 'hours';

    public string $rpoValue = '';

    public string $rpoUnit = 'hours';

    public string $mbco = '';

    public string $workarounds = '';

    public string $minimumStaff = '';

    public string $notes = '';

    public string $comment = '';

    public function mount(Assessment $assessment): void
    {
        $this->authorize('view', $assessment);
        $this->assessmentId = $assessment->id;
        $this->fillFrom($assessment);
    }

    #[Computed]
    public function assessment(): Assessment
    {
        return Assessment::query()->with(['process.owner', 'process.deputy', 'submitter', 'decider'])->findOrFail($this->assessmentId);
    }

    public function save(SaveAssessment $saveAssessment): void
    {
        $this->authorize('update', $this->assessment);
        $this->store($saveAssessment);
        unset($this->assessment);
    }

    public function submit(SaveAssessment $saveAssessment, SubmitAssessment $submitAssessment): void
    {
        $this->authorize('update', $this->assessment);
        $this->store($saveAssessment);

        try {
            $submitAssessment->handle($this->assessment, $this->actor());
        } catch (AssessmentLocked) {
            $this->addError('scores', __('bia::assessments.errors.locked'));
        } finally {
            unset($this->assessment);
        }
    }

    public function approve(DecideAssessment $decide): void
    {
        $this->decide($decide, true);
    }

    public function sendBack(DecideAssessment $decide): void
    {
        $this->decide($decide, false);
    }

    public function render(): View
    {
        $assessment = $this->assessment;
        $matrix = CurrentMatrix::load();
        $editable = auth()->user()?->can('update', $assessment) === true;
        $settings = BiaSettings::current();

        // A closed assessment shows the categories and horizons it was scored on, even retired ones.
        $scored = $assessment->scores()->get();
        $categories = $editable ? $matrix->categories : ImpactCategory::query()->withTrashed()->whereKey($scored->pluck('category_id')->unique()->all())->orderBy('sort_order')->orderBy('name')->get();
        $horizons = $editable ? $matrix->horizons : TimeHorizon::query()->withTrashed()->whereKey($scored->pluck('horizon_id')->unique()->all())->orderBy('minutes')->get();
        $computed = $matrix->read($matrix->clean($this->scores))->mtpd($settings->unacceptable_level);

        return view('bia::livewire.assessments.show', [
            'assessment' => $assessment,
            'process' => $assessment->process,
            'categories' => $categories,
            'horizons' => $horizons,
            'editable' => $editable,
            'canDecide' => auth()->user()?->can('decide', $assessment) === true,
            'level' => $assessment->unacceptable_level ?? $settings->unacceptable_level,
            'computedMtpd' => $computed,
            'units' => array_keys(Duration::UNITS),
        ])->title($assessment->process->name.' · v'.$assessment->version);
    }

    private function store(SaveAssessment $saveAssessment): void
    {
        $this->validate([
            'scores.*' => ['nullable', Rule::in(['', '1', '2', '3', '4', '5'])],
            'mtpdValue' => [Rule::requiredIf($this->overrideMtpd), 'nullable', 'integer', 'min:1', 'max:100000'],
            'mtpdReason' => [Rule::requiredIf($this->overrideMtpd), 'nullable', 'string', 'max:2000'],
            'rtoValue' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'rpoValue' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'mtpdUnit' => ['required', Rule::in(array_keys(Duration::UNITS))],
            'rtoUnit' => ['required', Rule::in(array_keys(Duration::UNITS))],
            'rpoUnit' => ['required', Rule::in(array_keys(Duration::UNITS))],
            'mbco' => ['nullable', 'string', 'max:5000'],
            'workarounds' => ['nullable', 'string', 'max:5000'],
            'minimumStaff' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $saveAssessment->handle($this->assessment, $this->scores, [
                'mtpd_override' => $this->overrideMtpd ? Duration::toMinutes($this->mtpdValue, $this->mtpdUnit) : null,
                'mtpd_reason' => $this->overrideMtpd ? $this->mtpdReason : null,
                'rto_minutes' => Duration::toMinutes($this->rtoValue, $this->rtoUnit),
                'rpo_minutes' => Duration::toMinutes($this->rpoValue, $this->rpoUnit),
                'mbco' => $this->mbco,
                'workarounds' => $this->workarounds,
                'minimum_staff' => $this->minimumStaff !== '' ? (int) $this->minimumStaff : null,
                'notes' => $this->notes,
            ]);
        } catch (ValidationException $exception) {
            $errors = [];

            foreach ($exception->errors() as $key => $messages) {
                $errors[match ($key) {
                    'rto_minutes' => 'rtoValue',
                    'mtpd_reason' => 'mtpdReason',
                    default => $key,
                }] = $messages;
            }

            throw ValidationException::withMessages($errors);
        } catch (AssessmentLocked) {
            throw ValidationException::withMessages(['scores' => __('bia::assessments.errors.locked')]);
        }
    }

    private function decide(DecideAssessment $decide, bool $approve): void
    {
        $this->authorize('decide', $this->assessment);
        $this->validate(['comment' => ['nullable', 'string', 'max:5000']]);

        try {
            $decide->handle($this->assessment, $this->actor(), $approve, $this->comment);
        } catch (AuthorizationException) {
            $this->addError('comment', __('bia::assessments.errors.cannot_decide'));

            return;
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['comment' => $exception->errors()['comment'] ?? []]);
        } finally {
            unset($this->assessment);
        }

        $this->reset('comment');
    }

    private function fillFrom(Assessment $assessment): void
    {
        $this->scores = array_map(strval(...), $assessment->scoreMap());

        $matrix = CurrentMatrix::load();
        $computed = $matrix->read($assessment->scoreMap())->mtpd(BiaSettings::current()->unacceptable_level);
        $this->overrideMtpd = $assessment->mtpd_reason !== null && $assessment->mtpd_minutes !== $computed;
        ['value' => $this->mtpdValue, 'unit' => $this->mtpdUnit] = Duration::split($this->overrideMtpd ? $assessment->mtpd_minutes : null);
        $this->mtpdReason = $assessment->mtpd_reason ?? '';
        ['value' => $this->rtoValue, 'unit' => $this->rtoUnit] = Duration::split($assessment->rto_minutes);
        ['value' => $this->rpoValue, 'unit' => $this->rpoUnit] = Duration::split($assessment->rpo_minutes);
        $this->mbco = $assessment->mbco ?? '';
        $this->workarounds = $assessment->workarounds ?? '';
        $this->minimumStaff = $assessment->minimum_staff !== null ? (string) $assessment->minimum_staff : '';
        $this->notes = $assessment->notes ?? '';
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
