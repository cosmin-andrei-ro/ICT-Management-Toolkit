<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Core\Application\Collaboration\StoreAttachment;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Application\Actions\ManageLicenseSeats;
use Modules\Itam\Application\Actions\RevealLicenseKey;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\LicenseAssignment;

/**
 * One licence: its terms, who or what uses its seats, its key on request, and
 * its documents.
 *
 * @property-read License $license Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowLicense extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $licenseId = '';

    public string $seatSearch = '';

    public string $revealCode = '';

    /** Shown after a valid code, until hidden or the page is left. */
    #[Locked]
    public ?string $revealedKey = null;

    /** @var list<UploadedFile> */
    public array $files = [];

    public function mount(License $license): void
    {
        $this->authorize('view', $license);
        $this->licenseId = $license->id;
    }

    #[Computed]
    public function license(): License
    {
        return License::query()->with(['product', 'supplier', 'contract'])->findOrFail($this->licenseId);
    }

    public function givePerson(string $userId, ManageLicenseSeats $seats, OrganizationContext $context): void
    {
        $this->authorize('assign', $this->license);
        $person = User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->find($userId);

        $this->seat(fn () => $person !== null ? $seats->giveToPerson($this->license, $person, $this->actor()) : throw new InvalidArgumentException('Unknown person.'));
    }

    public function giveDevice(string $assetId, ManageLicenseSeats $seats): void
    {
        $this->authorize('assign', $this->license);
        // Scoped lookup: an asset of another organization is not found.
        $asset = Asset::query()->find($assetId);

        $this->seat(fn () => $asset !== null ? $seats->giveToDevice($this->license, $asset, $this->actor()) : throw new InvalidArgumentException('Unknown device.'));
    }

    public function releaseSeat(string $seatId, ManageLicenseSeats $seats): void
    {
        $this->authorize('assign', $this->license);
        $seats->release(LicenseAssignment::query()->where('license_id', $this->licenseId)->findOrFail($seatId), $this->actor());
    }

    public function revealKey(RevealLicenseKey $reveal): void
    {
        $license = $this->license;
        $this->authorize('revealKey', $license);
        $this->validate(['revealCode' => ['required', 'string', 'max:32']]);

        $key = $reveal->handle($license, $this->actor(), $this->revealCode);
        $this->reset('revealCode');

        if ($key === null) {
            throw ValidationException::withMessages(['revealCode' => __($reveal->isThrottled($this->actor()) ? 'itam::licenses.ui.reveal_throttled' : 'itam::licenses.ui.reveal_invalid')]);
        }

        $this->revealedKey = $key;
    }

    public function hideKey(): void
    {
        $this->revealedKey = null;
    }

    public function uploadDocuments(StoreAttachment $storeAttachment): void
    {
        $license = $this->license;
        $this->authorize('update', $license);
        $this->validate([
            'files' => ['required', 'array', 'max:5'],
            'files.*' => ['file'],
        ]);

        try {
            foreach ($this->files as $file) {
                $storeAttachment->handle($license, $this->actor(), $file, false);
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['files' => $exception->errors()['file'] ?? [__('core::attachments.invalid')]]);
        }

        $this->reset('files');
    }

    public function removeDocument(string $attachmentId): void
    {
        $license = $this->license;
        $this->authorize('update', $license);

        Attachment::query()
            ->where('attachable_type', $license->getMorphClass())
            ->where('attachable_id', $license->id)
            ->findOrFail($attachmentId)
            ->delete();
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->license);
        $this->license->delete();
        $this->redirectRoute('itam.licenses.index');
    }

    public function render(OrganizationContext $context): View
    {
        $license = $this->license;
        $search = trim($this->seatSearch);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';
        $seats = $license->assignments()->with(['user', 'asset.category'])->orderBy('assigned_at')->get();
        $taken = $seats->pluck($license->model->forDevices() ? 'asset_id' : 'user_id')->filter()->all();

        $candidates = collect();

        if (mb_strlen($search) >= 2) {
            $candidates = $license->model->forDevices()
                ? Asset::query()->with('category')->where('status', '!=', AssetStatus::Disposed->value)->search($search)->whereKeyNot($taken)->orderBy('asset_tag')->limit(10)->get()
                : User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))
                    ->where(fn ($query) => $query->whereLike('name', $like)->orWhereLike('email', $like))
                    ->whereKeyNot($taken)->orderBy('name')->limit(10)->get(['id', 'name', 'email']);
        }

        return view('itam::livewire.licenses.show', [
            'license' => $license,
            'expired' => $license->isExpiredOn(CarbonImmutable::today()),
            'seats' => $seats,
            'candidates' => $candidates,
            'documents' => $license->attachments()->get(),
        ])->title($license->product->name.' · '.$license->name);
    }

    private function seat(callable $give): void
    {
        try {
            $give();
            $this->reset('seatSearch');
        } catch (InvalidArgumentException) {
            $this->addError('seatSearch', __('itam::licenses.ui.invalid_seat'));
        } finally {
            unset($this->license);
        }
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
