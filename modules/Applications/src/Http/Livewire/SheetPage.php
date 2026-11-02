<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Livewire;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Applications\Application\Actions\DecideSheet;
use Modules\Applications\Application\Actions\SaveSheet;
use Modules\Applications\Application\Actions\SubmitSheet;
use Modules\Applications\Application\Options\OptionCatalog;
use Modules\Applications\Application\Sheets\SheetApprovers;
use Modules\Applications\Application\Sheets\SheetForm;
use Modules\Applications\Domain\Enums\ApprovalRole;
use Modules\Applications\Domain\Exceptions\SheetLocked;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Domain\Sheet\FieldType;
use Modules\Applications\Domain\Sheet\SheetSchema;
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

    public function render(OptionCatalog $options, SheetForm $sheetForm, SheetApprovers $approvers, OrganizationContext $context): View
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
            $sections[$section->key] = $sheetForm->fromForm($section, is_array($input) ? $input : [], 'form.'.$section->key);
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
            $this->form[$section->key] = $sheetForm->toForm($section, $this->sheet->section($section->key));
        }
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
