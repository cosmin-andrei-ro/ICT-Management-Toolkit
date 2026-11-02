<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Livewire;

use Carbon\CarbonImmutable;
use Closure;
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
use Modules\Core\Application\Collaboration\StoreAttachment;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Application\Actions\AssignAsset;
use Modules\Itam\Application\Actions\ChangeAssetStatus;
use Modules\Itam\Application\Actions\DisposeAsset;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Enums\DisposalMethod;
use Modules\Itam\Domain\Exceptions\AssetTransitionNotAllowed;
use Modules\Itam\Domain\Models\Asset;

/**
 * One asset: its details, who has it, its life cycle, the configuration item it
 * is in the CMDB, its documents and its history.
 *
 * @property-read Asset $asset Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowAsset extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $assetId = '';

    public string $holderSearch = '';

    public string $holderId = '';

    public string $assignLocationId = '';

    public string $assignNotes = '';

    public string $statusLocationId = '';

    public string $statusNotes = '';

    public string $disposedOn = '';

    public string $disposalMethod = '';

    /** "1" wiped, "0" not wiped, "" stores no data. */
    public string $dataWiped = '';

    public string $disposalNotes = '';

    public string $itemSearch = '';

    /** @var list<UploadedFile> */
    public array $files = [];

    public function mount(Asset $asset): void
    {
        $this->authorize('view', $asset);
        $this->assetId = $asset->id;
        $this->disposedOn = now()->toDateString();
    }

    #[Computed]
    public function asset(): Asset
    {
        return Asset::query()->with(['category', 'holder', 'location', 'supplier', 'contract'])->findOrFail($this->assetId);
    }

    public function assignAsset(AssignAsset $assign): void
    {
        $this->authorize('assign', $this->asset);
        $this->validate([
            'holderId' => ['required', 'uuid'],
            'assignLocationId' => ['nullable', 'uuid'],
            'assignNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Scoped to the organization's members: nobody else is found.
        $holder = $this->members()->whereKey($this->holderId)->first();

        if ($holder === null) {
            $this->addError('holderId', __('itam::assets.ui.invalid_holder'));

            return;
        }

        if ($this->attempt(fn () => $assign->handle($this->asset, $holder, $this->actor(), $this->assignLocationId ?: null, $this->assignNotes), 'holderId')) {
            $this->reset('holderSearch', 'holderId', 'assignLocationId', 'assignNotes');
        }
    }

    public function changeStatus(string $status, ChangeAssetStatus $change): void
    {
        $this->authorize(in_array($status, [AssetStatus::InStock->value, AssetStatus::InRepair->value, AssetStatus::Deployed->value], true) ? 'assign' : 'update', $this->asset);
        $this->validate([
            'statusLocationId' => ['nullable', 'uuid'],
            'statusNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = AssetStatus::tryFrom($status);

        if ($target === null) {
            return;
        }

        if ($this->attempt(fn () => $change->handle($this->asset, $target, $this->actor(), $this->statusLocationId ?: null, $this->statusNotes), 'status')) {
            $this->reset('statusLocationId', 'statusNotes');
        }
    }

    public function dispose(DisposeAsset $dispose): void
    {
        $this->authorize('update', $this->asset);
        $this->validate([
            'disposedOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'disposalMethod' => ['required', Rule::enum(DisposalMethod::class)],
            'dataWiped' => ['nullable', Rule::in(['0', '1'])],
            'disposalNotes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->attempt(fn () => $dispose->handle(
            $this->asset,
            CarbonImmutable::parse($this->disposedOn),
            DisposalMethod::from($this->disposalMethod),
            $this->dataWiped === '' ? null : $this->dataWiped === '1',
            $this->disposalNotes,
        ), 'disposalMethod');
    }

    /**
     * An asset is one configuration item at most: linking another replaces it.
     */
    public function linkItem(string $itemId, ConfigurationItems $items): void
    {
        $asset = $this->asset;
        $this->authorize('update', $asset);

        try {
            foreach ($items->linkedTo($asset) as $linked) {
                if ($linked->id !== $itemId) {
                    $items->unlink($asset, $linked->id, $this->actor());
                }
            }

            $items->link($asset, $itemId, $this->actor());
        } catch (InvalidArgumentException) {
            $this->addError('itemSearch', __('itam::assets.ui.invalid_item'));

            return;
        }

        $this->reset('itemSearch');
    }

    public function unlinkItem(string $itemId, ConfigurationItems $items): void
    {
        $this->authorize('update', $this->asset);
        $items->unlink($this->asset, $itemId, $this->actor());
    }

    public function uploadDocuments(StoreAttachment $storeAttachment): void
    {
        $asset = $this->asset;
        $this->authorize('update', $asset);
        $this->validate([
            'files' => ['required', 'array', 'max:5'],
            'files.*' => ['file'],
        ]);

        try {
            foreach ($this->files as $file) {
                $storeAttachment->handle($asset, $this->actor(), $file, false);
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['files' => $exception->errors()['file'] ?? [__('core::attachments.invalid')]]);
        }

        $this->reset('files');
    }

    public function removeDocument(string $attachmentId): void
    {
        $asset = $this->asset;
        $this->authorize('update', $asset);

        Attachment::query()
            ->where('attachable_type', $asset->getMorphClass())
            ->where('attachable_id', $asset->id)
            ->findOrFail($attachmentId)
            ->delete();
    }

    public function delete(): void
    {
        $asset = $this->asset;
        $this->authorize('delete', $asset);

        if ($asset->assigned_to_id !== null) {
            $this->addError('status', __('itam::assets.ui.delete_held'));

            return;
        }

        $asset->delete();
        $this->redirectRoute('itam.assets.index');
    }

    public function render(ConfigurationItems $items): View
    {
        $asset = $this->asset;
        $search = trim($this->holderSearch);
        $itemSearch = trim($this->itemSearch);

        return view('itam::livewire.show', [
            'asset' => $asset,
            'fields' => $asset->category->schema()->fields,
            // Handing out and disposal have their own forms.
            'moves' => array_values(array_filter($asset->status->next(), fn (AssetStatus $status): bool => $status !== AssetStatus::Disposed && ($status !== AssetStatus::Deployed || $asset->assigned_to_id !== null))),
            'canDispose' => $asset->status->canBecome(AssetStatus::Disposed),
            'heldSince' => $asset->assigned_to_id !== null ? $asset->assignments()->whereNull('returned_at')->first()?->assigned_at : null,
            'disposalMethods' => DisposalMethod::cases(),
            'locations' => Location::paths(),
            'people' => mb_strlen($search) >= 2
                ? $this->members()->where(fn ($query) => $query->whereLike('name', '%'.self::escapeLike($search).'%')->orWhereLike('email', '%'.self::escapeLike($search).'%'))->orderBy('name')->limit(10)->get(['id', 'name', 'email'])
                : collect(),
            'assignments' => $asset->assignments()->with(['user', 'assigner', 'receiver'])->limit(50)->get(),
            'documents' => $asset->attachments()->get(),
            'cmdbAvailable' => $items->isAvailable(),
            'linkedItems' => $items->linkedTo($asset),
            'itemCandidates' => mb_strlen($itemSearch) >= 2 ? $items->search($itemSearch) : [],
        ])->title($asset->asset_tag.' '.$asset->displayName());
    }

    /**
     * @return Builder<User>
     */
    private function members(): Builder
    {
        $organizationId = app(OrganizationContext::class)->requireId();

        return User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($organizationId));
    }

    private function attempt(Closure $action, string $errorKey): bool
    {
        try {
            $action();

            return true;
        } catch (AssetTransitionNotAllowed) {
            $this->addError($errorKey, __('itam::assets.ui.not_now'));
        } catch (InvalidArgumentException) {
            $this->addError($errorKey, __('itam::assets.ui.invalid_reference'));
        } finally {
            unset($this->asset);
        }

        return false;
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
