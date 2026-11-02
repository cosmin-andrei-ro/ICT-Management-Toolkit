<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Audit\Application\Actions\SaveFinding;
use Modules\Audit\Domain\Enums\FindingSeverity;
use Modules\Audit\Domain\Enums\TestResult;
use Modules\Audit\Domain\Models\Audit;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\ControlTest;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Raises a finding in an audit (?audit=) or from a control test (?test=),
 * or changes one.
 */
#[Layout('core::components.layouts.app')]
final class FindingForm extends Component
{
    #[Locked]
    public ?string $findingId = null;

    #[Locked]
    public ?string $auditId = null;

    #[Locked]
    public ?string $testId = null;

    public string $title = '';

    public string $description = '';

    public string $severity = 'minor_nonconformity';

    public string $ownerId = '';

    public string $dueOn = '';

    public string $controlId = '';

    public string $requirementId = '';

    public string $requirementSearch = '';

    public function mount(?Finding $finding = null): void
    {
        if ($finding !== null && $finding->exists) {
            $this->authorize('update', $finding);
            $this->findingId = $finding->id;
            $this->title = $finding->title;
            $this->description = $finding->description;
            $this->severity = $finding->severity->value;
            $this->ownerId = $finding->owner_id ?? '';
            $this->dueOn = $finding->due_on?->toDateString() ?? '';
            $this->controlId = $finding->control_id ?? '';
            $this->requirementId = $finding->requirement_id ?? '';

            return;
        }

        $this->authorize('create', Finding::class);
        $audit = request()->query('audit');
        $test = request()->query('test');

        if (is_string($audit) && $audit !== '') {
            $this->auditId = Audit::query()->findOrFail($audit)->id;
        } elseif (is_string($test) && $test !== '') {
            // A test that was not fully effective: the finding concerns its control, owned by the control's owner.
            $controlTest = ControlTest::query()->findOrFail($test);
            $control = Control::query()->findOrFail($controlTest->control_id);
            $this->testId = $controlTest->id;
            $this->controlId = $control->id;
            $this->ownerId = $control->owner_id ?? '';
            $this->title = __('audit::audits.findings.from_test_title', ['control' => $control->code().' '.$control->name]);
            $this->description = trim($controlTest->method."\n\n".($controlTest->notes ?? ''));
            $this->severity = $controlTest->result === TestResult::Ineffective ? FindingSeverity::MajorNonconformity->value : FindingSeverity::MinorNonconformity->value;
        } else {
            abort(404);
        }
    }

    public function save(SaveFinding $save, OrganizationContext $context): void
    {
        $finding = $this->findingId !== null ? Finding::query()->findOrFail($this->findingId) : null;
        $this->authorize($finding !== null ? 'update' : 'create', $finding ?? Finding::class);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'severity' => ['required', Rule::enum(FindingSeverity::class)],
            'ownerId' => ['required', 'uuid', Rule::in($this->people($context)->pluck('id')->all())],
            'dueOn' => ['nullable', 'date_format:Y-m-d'],
            'controlId' => ['nullable', 'uuid'],
            'requirementId' => ['nullable', 'uuid'],
        ]);

        try {
            $saved = $save->handle($finding, [
                'title' => $this->title,
                'description' => $this->description,
                'severity' => FindingSeverity::from($this->severity),
                'owner_id' => $this->ownerId,
                'due_on' => $this->dueOn !== '' ? (CarbonImmutable::createFromFormat('!Y-m-d', $this->dueOn) ?: null) : null,
                'control_id' => $this->controlId !== '' ? $this->controlId : null,
                'requirement_id' => $this->requirementId !== '' ? $this->requirementId : null,
            ], $this->actor(),
                $this->auditId !== null ? Audit::query()->findOrFail($this->auditId) : null,
                $this->testId !== null ? ControlTest::query()->findOrFail($this->testId) : null,
            );
        } catch (InvalidArgumentException) {
            $this->addError('controlId', __('audit::audits.errors.invalid'));

            return;
        }

        $this->redirectRoute('audit.findings.show', $saved);
    }

    public function chooseRequirement(string $requirementId): void
    {
        $this->requirementId = Requirement::query()->findOrFail($requirementId)->id;
        $this->requirementSearch = '';
    }

    public function clearRequirement(): void
    {
        $this->requirementId = '';
    }

    public function render(OrganizationContext $context): View
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim(mb_substr($this->requirementSearch, 0, 200))).'%';

        return view('audit::livewire.findings.form', [
            'people' => $this->people($context),
            'severities' => FindingSeverity::cases(),
            'controls' => Control::query()->where('status', '!=', 'retired')->orderBy('number')->get(['id', 'number', 'name']),
            'requirement' => $this->requirementId !== '' ? Requirement::query()->with('framework')->find($this->requirementId) : null,
            'candidates' => trim($this->requirementSearch) !== ''
                ? Requirement::query()->with('framework')->whereRelation('framework', 'is_active', true)->where(fn ($query) => $query->whereLike('reference', $like)->orWhereLike('title', $like))->orderBy('framework_id')->orderBy('position')->limit(20)->get()
                : collect(),
            'audit' => $this->auditId !== null ? Audit::query()->find($this->auditId) : null,
        ])->title(__($this->findingId !== null ? 'audit::audits.findings.edit' : 'audit::audits.findings.new'));
    }

    /**
     * @return Collection<int, User>
     */
    private function people(OrganizationContext $context): Collection
    {
        return User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
