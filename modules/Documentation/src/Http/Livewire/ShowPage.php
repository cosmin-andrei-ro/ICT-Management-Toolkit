<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Http\Livewire;

use Illuminate\Contracts\View\View;
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
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\User;
use Modules\Documentation\Application\Actions\DeletePage;
use Modules\Documentation\Application\Actions\ManagePageLinks;
use Modules\Documentation\Domain\Models\Page;

/**
 * A page to read, with its sub-pages, what it is about and its files.
 *
 * @property-read Page $page Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowPage extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $pageId = '';

    public string $linkType = '';

    public string $linkSearch = '';

    /** @var list<UploadedFile> */
    public array $files = [];

    public function mount(Page $page): void
    {
        $this->authorize('view', $page);
        $this->pageId = $page->id;
    }

    #[Computed]
    public function page(): Page
    {
        return Page::query()->with(['space', 'editor', 'parent'])->findOrFail($this->pageId);
    }

    public function link(string $recordId, ManagePageLinks $links): void
    {
        $this->authorize('update', $this->page);
        $this->validate(['linkType' => ['required', Rule::in(array_keys(app(LinkedRecords::class)->searchable()))]]);

        try {
            $links->link($this->page, $this->linkType, $recordId, $this->actor());
        } catch (InvalidArgumentException) {
            $this->addError('linkSearch', __('documentation::docs.pages.link_failed'));
        }
    }

    public function unlink(string $linkId, ManagePageLinks $links): void
    {
        $this->authorize('update', $this->page);
        $links->unlink($this->page, $linkId, $this->actor());
    }

    public function uploadFiles(StoreAttachment $store): void
    {
        $page = $this->page;
        $this->authorize('update', $page);
        $this->validate(['files' => ['required', 'array', 'max:5'], 'files.*' => ['file']]);

        try {
            foreach ($this->files as $file) {
                $store->handle($page, $this->actor(), $file, false);
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['files' => $exception->errors()['file'] ?? [__('core::attachments.invalid')]]);
        }

        $this->reset('files');
    }

    public function removeFile(string $attachmentId): void
    {
        $page = $this->page;
        $this->authorize('update', $page);
        Attachment::query()->where('attachable_type', $page->getMorphClass())->where('attachable_id', $page->id)->findOrFail($attachmentId)->delete();
    }

    public function delete(DeletePage $delete): void
    {
        $page = $this->page;
        $this->authorize('delete', $page);
        $delete->handle($page);
        $this->redirectRoute('documentation.spaces.show', $page->space_id);
    }

    public function render(LinkedRecords $records): View
    {
        $page = $this->page;
        $this->authorize('view', $page);
        $viewer = $this->actor();
        $canEdit = $viewer->can('update', $page);
        $links = $page->links()->get();
        $resolved = [];

        // Records shown as the viewer may open them; the others by the title kept when linked.
        foreach ($links->groupBy('linked_type') as $type => $group) {
            foreach ($records->resolve((string) $type, array_values(array_filter($group->pluck('linked_id')->all(), is_string(...))), $viewer) as $record) {
                $resolved[$type.':'.$record->id] = $record;
            }
        }

        $breadcrumbs = [];

        for ($ancestor = $page->parent, $depth = 0; $ancestor !== null && $depth < 20; $ancestor = $ancestor->parent, $depth++) {
            array_unshift($breadcrumbs, $ancestor);
        }

        return view('documentation::livewire.page', [
            'page' => $page,
            'breadcrumbs' => $breadcrumbs,
            'children' => $page->children()->get(['id', 'title']),
            'links' => $links,
            'resolved' => $resolved,
            'kindLabels' => $records->searchable(),
            'found' => $canEdit && $this->linkType !== '' ? $records->search($this->linkType, $this->linkSearch, $viewer) : [],
            'attachments' => $page->attachments()->with('uploader')->get(),
            'canEdit' => $canEdit,
            'timezone' => config()->string('app.display_timezone'),
        ])->title($page->title);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
