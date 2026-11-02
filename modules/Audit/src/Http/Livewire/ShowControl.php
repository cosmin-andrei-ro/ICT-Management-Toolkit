<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Audit\Application\Actions\ManageEvidence;
use Modules\Audit\Application\Actions\MapControl;
use Modules\Audit\Application\Actions\RecordControlTest;
use Modules\Audit\Application\Actions\SaveControl;
use Modules\Audit\Domain\Enums\ControlStatus;
use Modules\Audit\Domain\Enums\EvidenceKind;
use Modules\Audit\Domain\Enums\TestResult;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Evidence;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;

/**
 * One control: how far it is implemented (kept by its owner), the
 * requirements of every framework it helps to meet, the evidence that it
 * works and its periodic tests.
 *
 * @property-read Control $control Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowControl extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $controlId = '';

    public string $status = '';

    public string $notes = '';

    public bool $implementationSaved = false;

    public string $mapFrameworkId = '';

    public string $mapSearch = '';

    public string $evidenceType = '';

    public string $evidenceSearch = '';

    public string $evidenceNote = '';

    public ?UploadedFile $document = null;

    public string $documentTitle = '';

    public string $documentDescription = '';

    public string $documentValidUntil = '';

    public string $testedOn = '';

    public string $testResult = 'effective';

    public string $testMethod = '';

    public string $testNotes = '';

    public ?UploadedFile $testDocument = null;

    public function mount(Control $control): void
    {
        $this->authorize('view', $control);
        $this->controlId = $control->id;
        $this->status = $control->status->value;
        $this->notes = $control->implementation_notes ?? '';
        $this->testedOn = CarbonImmutable::today()->toDateString();
    }

    #[Computed]
    public function control(): Control
    {
        return Control::query()->with(['owner', 'requirements.framework'])->findOrFail($this->controlId);
    }

    public function saveImplementation(SaveControl $save): void
    {
        $this->authorize('implement', $this->control);
        $this->validate([
            'status' => ['required', Rule::enum(ControlStatus::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $save->implementation($this->control, ControlStatus::from($this->status), $this->notes);
        unset($this->control);
        $this->implementationSaved = true;
    }

    public function mapRequirement(string $requirementId, MapControl $map): void
    {
        $this->authorize('update', $this->control);

        try {
            $map->add($this->control, $requirementId, $this->actor());
        } catch (InvalidArgumentException) {
            $this->addError('mapSearch', __('audit::compliance.errors.invalid'));

            return;
        }

        unset($this->control);
    }

    public function unmapRequirement(string $requirementId, MapControl $map): void
    {
        $this->authorize('update', $this->control);
        $map->remove($this->control, $requirementId, $this->actor());
        unset($this->control);
    }

    public function linkEvidence(string $recordId, ManageEvidence $evidence): void
    {
        $this->authorize('implement', $this->control);
        $this->validate([
            'evidenceType' => ['required', Rule::in(array_keys(app(LinkedRecords::class)->searchable()))],
            'evidenceNote' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $evidence->link($this->control, $this->evidenceType, $recordId, $this->evidenceNote, $this->actor());
        } catch (InvalidArgumentException) {
            $this->addError('evidenceSearch', __('audit::evidence.errors.link'));

            return;
        }

        $this->reset('evidenceNote');
        unset($this->control);
    }

    public function uploadEvidence(ManageEvidence $evidence): void
    {
        $this->authorize('implement', $this->control);
        $this->validate([
            'document' => ['required', 'file'],
            'documentTitle' => ['nullable', 'string', 'max:255'],
            'documentDescription' => ['nullable', 'string', 'max:2000'],
            'documentValidUntil' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! $this->document instanceof UploadedFile) {
            return;
        }

        try {
            $evidence->upload($this->control, $this->document, $this->documentTitle, $this->documentDescription, $this->documentValidUntil !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $this->documentValidUntil) ?: null : null, $this->actor());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['document' => $exception->errors()['file'] ?? [__('core::attachments.invalid')]]);
        }

        $this->reset('document', 'documentTitle', 'documentDescription', 'documentValidUntil');
        unset($this->control);
    }

    public function removeEvidence(string $evidenceId, ManageEvidence $evidence): void
    {
        $this->authorize('implement', $this->control);
        $evidence->remove(Evidence::query()->where('control_id', $this->controlId)->findOrFail($evidenceId));
        unset($this->control);
    }

    public function recordTest(RecordControlTest $record): void
    {
        $this->authorize('implement', $this->control);
        $this->validate([
            'testedOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'testResult' => ['required', Rule::enum(TestResult::class)],
            'testMethod' => ['required', 'string', 'max:5000'],
            'testNotes' => ['nullable', 'string', 'max:10000'],
            'testDocument' => ['nullable', 'file'],
        ]);

        try {
            $record->handle($this->control, CarbonImmutable::createFromFormat('!Y-m-d', $this->testedOn) ?: CarbonImmutable::today(), TestResult::from($this->testResult), $this->testMethod, $this->testNotes, $this->testDocument, $this->actor());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['testDocument' => $exception->errors()['file'] ?? [__('core::attachments.invalid')]]);
        }

        $this->reset('testMethod', 'testNotes', 'testDocument');
        $this->testResult = TestResult::Effective->value;
        unset($this->control);
    }

    public function delete(): void
    {
        $control = $this->control;
        $this->authorize('delete', $control);
        $control->delete();
        $this->redirectRoute('audit.controls.index');
    }

    public function render(LinkedRecords $records): View
    {
        $control = $this->control;
        $this->authorize('view', $control);
        $viewer = $this->actor();
        $canMap = $viewer->can('update', $control);
        $canImplement = $viewer->can('implement', $control);
        $evidence = $control->evidence()->with(['attachments', 'creator'])->get();
        $resolved = [];
        $kindLabels = $records->searchable();

        // Records shown as the viewer may open them; the others by the title kept when linked.
        foreach ($evidence->where('kind', EvidenceKind::Link)->groupBy('linked_type') as $type => $links) {
            foreach ($records->resolve((string) $type, array_values(array_filter($links->pluck('linked_id')->all(), is_string(...))), $viewer) as $record) {
                $resolved[$type.':'.$record->id] = $record;
            }
        }
        $candidates = collect();

        if ($canMap && $this->mapFrameworkId !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim(mb_substr($this->mapSearch, 0, 200))).'%';
            $candidates = Requirement::query()
                ->where('framework_id', $this->mapFrameworkId)
                ->whereNotIn('id', $control->requirements->modelKeys())
                ->when(trim($this->mapSearch) !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereLike('reference', $like)->orWhereLike('title', $like)))
                ->orderBy('position')
                ->limit(20)
                ->get();
        }

        return view('audit::livewire.controls.show', [
            'control' => $control,
            'mapped' => $control->requirements->sortBy([['framework.name', 'asc'], ['position', 'asc']])->groupBy(fn (Requirement $requirement): string => $requirement->framework->name),
            'frameworks' => $canMap ? Framework::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'candidates' => $candidates,
            'statuses' => ControlStatus::cases(),
            'canImplement' => $canImplement,
            'evidence' => $evidence,
            'resolved' => $resolved,
            'kinds' => $canImplement ? $kindLabels : [],
            'kindLabels' => $kindLabels,
            'timezone' => config()->string('app.display_timezone'),
            'found' => $canImplement && $this->evidenceType !== '' ? $records->search($this->evidenceType, $this->evidenceSearch, $viewer) : [],
            'tests' => $control->tests()->with(['tester', 'evidence.attachments'])->limit(50)->get(),
            'results' => TestResult::cases(),
            'today' => CarbonImmutable::today(),
        ])->title($control->code().' · '.$control->name);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
