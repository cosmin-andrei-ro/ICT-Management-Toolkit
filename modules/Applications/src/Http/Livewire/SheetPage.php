<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Livewire;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Applications\Application\Actions\DecideSheet;
use Modules\Applications\Application\Actions\SaveSheet;
use Modules\Applications\Application\Actions\SubmitSheet;
use Modules\Applications\Application\Options\OptionCatalog;
use Modules\Applications\Application\Sheets\SheetApprovers;
use Modules\Applications\Application\Sheets\SheetForm;
use Modules\Applications\Domain\Enums\ApprovalRole;
use Modules\Applications\Domain\Exceptions\SheetLocked;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Domain\Sheet\FieldType;
use Modules\Applications\Domain\Sheet\SheetSchema;
use Modules\Applications\Http\Controllers\DiagramImageController;
use Modules\Core\Application\Collaboration\StoreAttachment;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Links\ProcessSummary;
use Modules\Core\Domain\Links\RecoveryProfile;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * One version of an application's documentation sheet, chapter by chapter:
 * written by the owners while open, read by everyone who may see the
 * application, approved by the three approvers when major.
 *
 * @property-read Sheet $sheet Computed.
 */
#[Layout('core::components.layouts.app')]
final class SheetPage extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $sheetId = '';

    #[Url]
    public int $chapter = SheetSchema::DOCUMENT_CONTROL;

    /** @var array<string, mixed> The open chapter's values, by section key. */
    public array $form = [];

    public bool $isMajor = false;

    public string $changeSummary = '';

    public string $approvalRole = '';

    public string $comment = '';

    public string $itemSearch = '';

    public string $processSearch = '';

    /** Diagram image being attached (PNG, JPEG, WebP or GIF). */
    public ?TemporaryUploadedFile $diagramImage = null;

    /** Its source file (.drawio). */
    public ?TemporaryUploadedFile $diagramSource = null;

    public function mount(Sheet $sheet, SheetForm $sheetForm): void
    {
        $this->authorize('view', $sheet);
        $this->sheetId = $sheet->id;
        $this->isMajor = $sheet->is_major;
        $this->changeSummary = $sheet->change_summary ?? '';

        if (! array_key_exists($this->chapter, SheetSchema::chapters())) {
            $this->chapter = SheetSchema::DOCUMENT_CONTROL;
        }

        $this->loadChapter($sheetForm);
    }

    #[Computed]
    public function sheet(): Sheet
    {
        return Sheet::query()->with(['application', 'submitter', 'returner', 'approvals.user'])->findOrFail($this->sheetId);
    }

    /**
     * Saves the open chapter (when writing) and opens another one.
     */
    public function openChapter(int $chapter, SheetForm $sheetForm, SaveSheet $save): void
    {
        if (! array_key_exists($chapter, SheetSchema::chapters())) {
            return;
        }

        if ($this->canWrite() && ! $this->store($sheetForm, $save)) {
            return;
        }

        $this->chapter = $chapter;
        $this->resetErrorBag();
        $this->loadChapter($sheetForm);
    }

    public function save(SheetForm $sheetForm, SaveSheet $save): void
    {
        $this->authorize('update', $this->sheet);

        if ($this->store($sheetForm, $save)) {
            $this->dispatch('saved');
        }
    }

    public function addRow(string $section, string $field, SheetForm $sheetForm): void
    {
        $this->authorize('update', $this->sheet);
        $table = collect(SheetSchema::section($section)->fields ?? [])->first(fn ($candidate): bool => $candidate->key === $field && $candidate->type === FieldType::Table);
        $values = is_array($this->form[$section] ?? null) ? $this->form[$section] : [];
        $rows = is_array($values[$field] ?? null) ? $values[$field] : [];

        if ($table !== null && count($rows) < SheetForm::MAX_ROWS) {
            $rows[] = $sheetForm->emptyRow($table);
            $values[$field] = $rows;
            $this->form[$section] = $values;
        }
    }

    public function removeRow(string $section, string $field, int $index): void
    {
        $this->authorize('update', $this->sheet);
        $values = is_array($this->form[$section] ?? null) ? $this->form[$section] : [];
        $rows = $values[$field] ?? null;

        if (is_array($rows) && array_key_exists($index, $rows)) {
            unset($rows[$index]);
            $values[$field] = array_values($rows);
            $this->form[$section] = $values;
        }
    }

    /**
     * Stores the chosen image and source file with the application (so later
     * versions keep them) and puts them in the diagram field, then saves.
     */
    public function attachDiagram(string $section, string $field, StoreAttachment $store, SheetForm $sheetForm, SaveSheet $save): void
    {
        $this->authorize('update', $this->sheet);
        $target = collect(SheetSchema::chapters()[$this->chapter] ?? [])->first(fn ($candidate): bool => $candidate->key === $section);
        $diagram = collect($target->fields ?? [])->first(fn ($candidate): bool => $candidate->key === $field && $candidate->type === FieldType::Diagram);

        if ($diagram === null) {
            return;
        }

        $this->validate([
            'diagramImage' => ['nullable', 'file', 'required_without:diagramSource'],
            'diagramSource' => ['nullable', 'file'],
        ]);

        $application = $this->sheet->application;
        $values = is_array($this->form[$section] ?? null) ? $this->form[$section] : [];
        $files = is_array($values[$field] ?? null) ? $values[$field] : ['image' => '', 'source' => ''];

        if ($this->diagramImage !== null) {
            $files['image'] = $this->storeFile($store, $application, $this->diagramImage, 'diagramImage', true);
        }

        if ($this->diagramSource !== null) {
            $files['source'] = $this->storeFile($store, $application, $this->diagramSource, 'diagramSource', false);
        }

        $values[$field] = $files;
        $this->form[$section] = $values;
        $this->reset('diagramImage', 'diagramSource');
        $this->store($sheetForm, $save);
    }

    /**
     * Links a CMDB item to the version (5.2 Components), audited on the version.
     */
    public function linkItem(string $itemId, ConfigurationItems $items): void
    {
        $this->authorize('update', $this->sheet);

        try {
            $items->link($this->sheet, $itemId, $this->actor());
            $this->reset('itemSearch');
        } catch (InvalidArgumentException) {
            $this->addError('itemSearch', __('applications::sheet.errors.unknown_reference'));
        } finally {
            unset($this->sheet);
        }
    }

    public function unlinkItem(string $itemId, ConfigurationItems $items): void
    {
        $this->authorize('update', $this->sheet);
        $items->unlink($this->sheet, $itemId, $this->actor());
        unset($this->sheet);
    }

    /**
     * Adds a BIA process to 10.1 (saved with the chapter).
     */
    public function addProcess(string $section, string $field, string $processId, BusinessProcesses $processes): void
    {
        $this->authorize('update', $this->sheet);
        $values = is_array($this->form[$section] ?? null) ? $this->form[$section] : [];
        $ids = is_array($values[$field] ?? null) ? $values[$field] : null;

        if ($ids !== null && $processes->find([$processId]) !== [] && ! in_array($processId, $ids, true)) {
            $ids[] = $processId;
            $values[$field] = $ids;
            $this->form[$section] = $values;
            $this->reset('processSearch');
        }
    }

    public function removeProcess(string $section, string $field, string $processId): void
    {
        $this->authorize('update', $this->sheet);
        $values = is_array($this->form[$section] ?? null) ? $this->form[$section] : [];

        if (is_array($values[$field] ?? null)) {
            $values[$field] = array_values(array_filter($values[$field], fn (mixed $id): bool => $id !== $processId));
            $this->form[$section] = $values;
        }
    }

    /**
     * Fills the decommissioning checklist (15.2) with the template's steps.
     */
    public function addTemplateSteps(string $section, string $field, SheetForm $sheetForm): void
    {
        $this->authorize('update', $this->sheet);
        $table = collect(SheetSchema::section($section)->fields ?? [])->first(fn ($candidate): bool => $candidate->key === $field && $candidate->type === FieldType::Table);
        $steps = trans('applications::sheet.checklist_steps');

        if ($table === null || ! is_array($steps)) {
            return;
        }

        $values = is_array($this->form[$section] ?? null) ? $this->form[$section] : [];
        $rows = is_array($values[$field] ?? null) ? $values[$field] : [];

        foreach (array_values($steps) as $index => $activity) {
            $rows[] = [...$sheetForm->emptyRow($table), 'step' => (string) ($index + 1), 'activity' => is_string($activity) ? $activity : '', 'status' => 'not_started'];
        }

        $values[$field] = array_slice($rows, 0, SheetForm::MAX_ROWS);
        $this->form[$section] = $values;
    }

    public function removeDiagramFile(string $section, string $field, string $kind): void
    {
        $this->authorize('update', $this->sheet);
        $values = is_array($this->form[$section] ?? null) ? $this->form[$section] : [];

        if (in_array($kind, ['image', 'source'], true) && is_array($values[$field] ?? null)) {
            // The file stays with the application: versions in force may show it.
            $values[$field][$kind] = '';
            $this->form[$section] = $values;
        }
    }

    public function submit(SheetForm $sheetForm, SaveSheet $save, SubmitSheet $submitSheet): void
    {
        $this->authorize('update', $this->sheet);

        if (! $this->store($sheetForm, $save)) {
            return;
        }

        try {
            $inForce = $submitSheet->handle($this->sheet, $this->actor());
        } catch (SheetLocked) {
            $this->addError('sections', __('applications::sheet.errors.locked'));

            return;
        } finally {
            unset($this->sheet);
        }

        if ($inForce) {
            $this->redirectRoute('applications.show', $this->sheet->application_id);
        }
    }

    public function approve(DecideSheet $decide): void
    {
        $this->authorize('decide', $this->sheet);
        $role = ApprovalRole::tryFrom($this->approvalRole) ?? throw ValidationException::withMessages(['approvalRole' => __('applications::sheet.errors.role')]);
        $this->validate(['comment' => ['nullable', 'string', 'max:2000']]);

        try {
            $decide->approve($this->sheet, $role, $this->comment, $this->actor());
        } catch (AuthorizationException) {
            $this->addError('approvalRole', __('applications::sheet.errors.cannot_approve'));

            return;
        } finally {
            unset($this->sheet);
        }

        $this->reset('approvalRole', 'comment');
    }

    public function sendBack(DecideSheet $decide): void
    {
        $this->authorize('decide', $this->sheet);
        $this->validate(['comment' => ['required', 'string', 'max:5000']]);

        try {
            $decide->sendBack($this->sheet, $this->comment, $this->actor());
        } catch (AuthorizationException) {
            $this->addError('comment', __('applications::sheet.errors.cannot_approve'));

            return;
        } finally {
            unset($this->sheet);
        }

        $this->reset('approvalRole', 'comment');
    }

    public function render(OptionCatalog $options, SheetForm $sheetForm, SheetApprovers $approvers, OrganizationContext $context, ConfigurationItems $items, BusinessProcesses $processes, LinkedRecords $linkedRecords): View
    {
        $sheet = $this->sheet;
        $editable = $this->canWrite();
        $user = $this->actor();
        $sections = SheetSchema::chapters()[$this->chapter] ?? [];

        return view('applications::livewire.sheets.show', [
            'sheet' => $sheet,
            'application' => $sheet->application,
            'editable' => $editable,
            'chapters' => array_keys(SheetSchema::chapters()),
            'sections' => $sections,
            'incomplete' => array_map(fn ($section): string => $section->key, $sheetForm->incomplete($sheet)),
            'options' => $options,
            'myRoles' => $approvers->rolesFor($user, $sheet),
            'canDecide' => $user->can('decide', $sheet),
            'isFirst' => ! Sheet::query()->where('application_id', $sheet->application_id)->whereNotNull('major')->exists(),
            'people' => User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'contracts' => Contract::query()->orderBy('title')->limit(500)->get(),
            'matrix' => $sheetForm->matrixAxes($sheet),
            'integrations' => $this->rowsOf($editable, $sheet, 2, '2_4', 'integrations'),
            'firewallRules' => $this->rowsOf($editable, $sheet, 4, '4_3', 'rules'),
            'cmdbAvailable' => $items->isAvailable(),
            'linkedItems' => $items->linkedTo($sheet),
            'itemCandidates' => $editable && mb_strlen(trim($this->itemSearch)) >= 2 ? $items->search(trim($this->itemSearch)) : [],
            'processesAvailable' => $processes->isAvailable(),
            'processCandidates' => $editable && mb_strlen(trim($this->processSearch)) >= 2 ? $processes->search(trim($this->processSearch)) : [],
            'insights' => $this->insights($sheet, $editable, $items, $processes, $linkedRecords),
            'files' => Attachment::query()->where('attachable_type', 'applications.application')->where('attachable_id', $sheet->application_id)->get()->keyBy('id'),
        ])->title($sheet->application->name.' · '.__('applications::sheet.title'));
    }

    private function store(SheetForm $sheetForm, SaveSheet $save): bool
    {
        $sections = [];
        $rules = [
            'changeSummary' => ['nullable', 'string', 'max:2000'],
            'isMajor' => ['boolean'],
        ];

        foreach (SheetSchema::chapters()[$this->chapter] ?? [] as $section) {
            $rules += $sheetForm->rules($section, 'form.'.$section->key);
        }

        $this->validate($rules);

        foreach (SheetSchema::chapters()[$this->chapter] ?? [] as $section) {
            $input = $this->form[$section->key] ?? [];
            $sections[$section->key] = $sheetForm->fromForm($section, is_array($input) ? $input : [], 'form.'.$section->key, $this->sheet, $sections);
        }

        try {
            $save->handle($this->sheet, $sections, $this->isMajor, $this->changeSummary);
        } catch (SheetLocked) {
            $this->addError('sections', __('applications::sheet.errors.locked'));

            return false;
        } finally {
            unset($this->sheet);
        }

        $this->isMajor = $this->sheet->is_major;

        return true;
    }

    private function loadChapter(SheetForm $sheetForm): void
    {
        $this->form = [];

        foreach (SheetSchema::chapters()[$this->chapter] ?? [] as $section) {
            $this->form[$section->key] = $sheetForm->toForm($section, $this->sheet->section($section->key), $this->sheet);
        }
    }

    /**
     * @throws ValidationException keyed to $property
     */
    private function storeFile(StoreAttachment $store, Application $application, TemporaryUploadedFile $file, string $property, bool $image): string
    {
        try {
            // Shown in the page: raster images only, never SVG or HTML.
            if ($image && ! in_array($store->assertAcceptable($file), DiagramImageController::IMAGE_TYPES, true)) {
                throw ValidationException::withMessages(['file' => __('applications::sheet.errors.image_type')]);
            }

            return $store->handle($application, $this->actor(), $file, false)->id;
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([$property => $exception->errors()['file'] ?? [__('applications::sheet.errors.image_type')]]);
        }
    }

    /**
     * Table rows a generated diagram is drawn from: as typed while writing
     * their chapter, as saved otherwise.
     *
     * @return list<array<mixed>>
     */
    private function rowsOf(bool $editable, Sheet $sheet, int $chapter, string $section, string $field): array
    {
        $values = $editable && $this->chapter === $chapter && is_array($this->form[$section] ?? null)
            ? $this->form[$section]
            : $sheet->section($section);
        $rows = $values[$field] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, is_array(...))) : [];
    }

    /**
     * What other modules know, for the open chapter only: the BIA objectives of
     * the processes in 10.1, what the linked CMDB items depend on (10.3) and
     * the recovery plans covering them (10.5).
     *
     * @return array{processes: array<string, ProcessSummary>, dependencies: array<string, RecoveryProfile>, plans: list<LinkedRecord>}
     */
    private function insights(Sheet $sheet, bool $editable, ConfigurationItems $items, BusinessProcesses $processes, LinkedRecords $linkedRecords): array
    {
        $insights = ['processes' => [], 'dependencies' => [], 'plans' => []];

        if ($this->chapter !== 10) {
            return $insights;
        }

        $values = $editable && is_array($this->form['10_1'] ?? null) ? $this->form['10_1'] : $sheet->section('10_1');
        $processIds = array_values(array_filter(is_array($values['processes'] ?? null) ? $values['processes'] : [], is_string(...)));
        $insights['processes'] = $processIds !== [] ? $processes->find($processIds) : [];

        $itemIds = array_map(fn ($item): string => $item->id, $items->linkedTo($sheet));
        $insights['dependencies'] = $items->recoveryProfiles($items->dependencyIdsOf($itemIds));
        $insights['plans'] = $linkedRecords->resolve('bcm.plan_version', $items->recordIdsLinkedTo($itemIds, 'bcm.plan_version'), $this->actor());

        return $insights;
    }

    private function canWrite(): bool
    {
        return auth()->user()?->can('update', $this->sheet) === true;
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
