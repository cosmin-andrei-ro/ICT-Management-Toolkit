<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Core\Application\Collaboration\StoreAttachment;
use Modules\Core\Domain\Links\ContractDependents;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\User;

/**
 * One contract: terms, deadline and the signed documents.
 *
 * @property-read Contract $contract Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowContract extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $contractId = '';

    /** @var list<UploadedFile> */
    public array $files = [];

    public function mount(Contract $contract): void
    {
        $this->authorize('view', $contract);
        $this->contractId = $contract->id;
    }

    #[Computed]
    public function contract(): Contract
    {
        return Contract::query()->with(['supplier', 'owner'])->findOrFail($this->contractId);
    }

    public function uploadDocuments(StoreAttachment $storeAttachment): void
    {
        $contract = $this->contract;
        $this->authorize('update', $contract);
        $this->validate([
            'files' => ['required', 'array', 'max:5'],
            'files.*' => ['file'],
        ]);

        $uploader = auth()->user();
        abort_unless($uploader instanceof User, 401);

        try {
            foreach ($this->files as $file) {
                $storeAttachment->handle($contract, $uploader, $file, false);
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['files' => $exception->errors()['file'] ?? [__('core::attachments.invalid')]]);
        }

        $this->reset('files');
    }

    public function removeDocument(string $attachmentId): void
    {
        $contract = $this->contract;
        $this->authorize('update', $contract);

        Attachment::query()
            ->where('attachable_type', $contract->getMorphClass())
            ->where('attachable_id', $contract->id)
            ->findOrFail($attachmentId)
            ->delete();
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->contract);
        $this->contract->delete();
        $this->redirectRoute('core.contracts.index');
    }

    public function render(ContractDependents $dependents): View
    {
        $contract = $this->contract;
        $viewer = auth()->user();

        return view('core::livewire.contracts.show', [
            'contract' => $contract,
            'status' => $contract->statusOn(CarbonImmutable::today(), Contract::expiringDays()),
            'deadline' => $contract->deadline(),
            'documents' => $contract->attachments()->with('uploader')->get(),
            'dependents' => $viewer instanceof User ? $dependents->of($contract, $viewer) : [],
        ])->title($contract->title);
    }
}
