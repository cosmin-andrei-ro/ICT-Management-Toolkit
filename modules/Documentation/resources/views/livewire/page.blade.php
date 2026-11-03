<div class="grid gap-6 lg:grid-cols-4">
    <article class="space-y-4 lg:col-span-3">
        <header class="space-y-1">
            <nav aria-label="{{ __('documentation::docs.pages.breadcrumbs') }}" class="text-sm text-gray-500">
                <a href="{{ route('documentation.spaces.show', $page->space_id) }}" class="underline">{{ $page->space->name }}</a>
                @foreach ($breadcrumbs as $ancestor) › <a href="{{ route('documentation.pages.show', $ancestor) }}" wire:key="crumb-{{ $ancestor->id }}" class="underline">{{ $ancestor->title }}</a> @endforeach
            </nav>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h1 class="text-2xl font-semibold">{{ $showDraft ? $page->title : $page->displayTitle() }}</h1>
                <div class="flex flex-wrap gap-2">
                    @if ($page->isPublished())
                        <a href="{{ route('documentation.pages.print', $page) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __($pdfAvailable ? 'documentation::docs.publishing.print_pdf' : 'documentation::docs.publishing.print') }}</a>
                    @endif
                    @if ($canSeeHistory)
                        <a href="{{ route('documentation.pages.history', $page) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('documentation::docs.history.button') }}</a>
                    @endif
                    @if ($canEdit)
                        <a href="{{ route('documentation.pages.create', ['space' => $page->space_id, 'parent' => $page->id]) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('documentation::docs.pages.new_child') }}</a>
                        <a href="{{ route('documentation.pages.edit', $page) }}" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('documentation::docs.pages.edit') }}</a>
                    @endif
                </div>
            </div>
            <p class="text-xs text-gray-500">
                @if ($showDraft)
                    {{ __('documentation::docs.pages.version_line', ['version' => $page->version, 'name' => $page->editor?->name ?? '—', 'date' => $page->updated_at?->timezone($timezone)->format('Y-m-d H:i')]) }}
                @elseif ($publishedRecord)
                    {{ __('documentation::docs.publishing.published_line', ['version' => $publishedRecord->version, 'name' => $publishedRecord->author?->name ?? '—', 'date' => $page->published_at?->timezone($timezone)->format('Y-m-d')]) }}
                    @if ($publishedRecord->approver) · {{ __('documentation::docs.publishing.approved_by', ['name' => $publishedRecord->approver->name, 'date' => $publishedRecord->approved_at?->timezone($timezone)->format('Y-m-d')]) }} @endif
                @endif
                @if ($page->review_on) · <span @class(['font-semibold text-red-700' => $page->isReviewOverdue($today)])>{{ __('documentation::docs.publishing.review_on', ['date' => $page->review_on->toDateString()]) }}</span> @endif
            </p>
        </header>

        @if ($canEdit)
            @if ($page->rejection_note && $page->pending_version === null)
                <p class="rounded-md bg-red-50 p-3 text-sm text-red-800" role="alert">{{ __('documentation::docs.publishing.rejected', ['note' => $page->rejection_note]) }}</p>
            @endif
            @if ($page->hasDraft())
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-amber-50 p-3 text-sm text-amber-900" role="status">
                    <span>
                        @if (! $page->isPublished()) {{ __('documentation::docs.publishing.never_published') }}
                        @elseif ($showDraft) {{ __('documentation::docs.publishing.viewing_draft', ['version' => $page->version, 'published' => $page->published_version]) }}
                        @else {{ __('documentation::docs.publishing.has_draft', ['version' => $page->version]) }} @endif
                        @if ($page->pending_version) {{ __('documentation::docs.publishing.pending', ['version' => $page->pending_version, 'name' => $pending?->author?->name ?? '—']) }} @endif
                    </span>
                    <span class="flex flex-wrap gap-2">
                        @if ($page->isPublished())
                            <a href="{{ route('documentation.pages.show', ['page' => $page, 'draft' => $showDraft ? null : 1]) }}" class="underline">{{ __($showDraft ? 'documentation::docs.publishing.see_published' : 'documentation::docs.publishing.see_draft') }}</a>
                        @endif
                        @if ($page->space->requires_approval && $page->pending_version === null)
                            <button type="button" wire:click="submit" class="rounded-md bg-gray-900 px-3 py-1 text-xs font-semibold text-white">{{ __('documentation::docs.publishing.submit') }}</button>
                        @endif
                    </span>
                </div>
            @endif
        @endif

        @if ($canApprove)
            <section aria-labelledby="approval-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-amber-300">
                <h2 id="approval-heading" class="text-sm font-semibold">{{ __('documentation::docs.publishing.to_approve', ['version' => $page->pending_version, 'name' => $pending?->author?->name ?? '—']) }}</h2>
                <p class="text-xs text-gray-600">{{ __('documentation::docs.publishing.approve_help') }}</p>
                <label for="rejectionNote" class="block text-xs text-gray-600">{{ __('documentation::docs.publishing.rejection_note') }}</label>
                <textarea id="rejectionNote" wire:model="rejectionNote" rows="2" maxlength="2000" class="block w-full"></textarea>
                @error('rejectionNote') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="flex gap-2">
                    <button type="button" wire:click="approve" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('documentation::docs.publishing.approve') }}</button>
                    <button type="button" wire:click="reject" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('documentation::docs.publishing.reject') }}</button>
                    @if ($canSeeHistory) <a href="{{ route('documentation.pages.history', ['page' => $page, 'version' => $page->pending_version]) }}" class="self-center text-sm underline">{{ __('documentation::docs.publishing.see_changes') }}</a> @endif
                </div>
            </section>
        @endif

        {{-- Markdown::toSafeHtml(): raw HTML stripped, unsafe links dropped (CLAUDE.md §6). --}}
        <div class="markdown-content rounded-lg bg-white p-6 ring-1 ring-gray-200">{!! $showDraft ? $page->draftHtml() : $page->publishedHtml() !!}</div>

        @if ($children->isNotEmpty())
            <section aria-labelledby="children-heading" class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <h2 id="children-heading" class="text-sm font-semibold">{{ __('documentation::docs.pages.children') }}</h2>
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @foreach ($children as $child) <li wire:key="child-{{ $child->id }}"><a href="{{ route('documentation.pages.show', $child) }}" class="text-blue-700 underline">{{ $child->displayTitle() }}</a></li> @endforeach
                </ul>
            </section>
        @endif

        @if ($canEdit)
            <div class="flex flex-wrap justify-end gap-4">
                @if ($page->isPublished())
                    <button type="button" wire:click="markReviewed" class="text-sm text-blue-700 underline">{{ __('documentation::docs.publishing.mark_reviewed') }}</button>
                @endif
                <button type="button" wire:click="delete" wire:confirm="{{ __('documentation::docs.pages.delete_confirm') }}" class="text-sm text-red-700 underline">{{ __('documentation::docs.pages.delete') }}</button>
            </div>
        @endif
    </article>

    <aside class="space-y-4">
        <section aria-labelledby="links-heading" class="space-y-2 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
            <h2 id="links-heading" class="font-semibold">{{ __('documentation::docs.pages.links') }}</h2>
            <ul class="space-y-1">
                @forelse ($links as $link)
                    @php($record = $resolved[$link->linked_type.':'.$link->linked_id] ?? null)
                    <li wire:key="link-{{ $link->id }}" class="flex items-start justify-between gap-2">
                        <span>
                            <span class="block text-xs text-gray-500">{{ $kindLabels[$link->linked_type] ?? $link->linked_type }}</span>
                            @if ($record) <a href="{{ $record->url }}" class="text-blue-700 underline">{{ $record->title }}</a> @elseif ($canEdit) {{ $link->title }} @else <span class="text-gray-500">{{ __('documentation::docs.pages.link_unavailable') }}</span> @endif
                        </span>
                        @if ($canEdit) <button type="button" wire:click="unlink('{{ $link->id }}')" class="text-xs text-red-700 underline">{{ __('documentation::docs.remove') }}</button> @endif
                    </li>
                @empty
                    <li class="text-gray-500">{{ __('documentation::docs.pages.no_links') }}</li>
                @endforelse
            </ul>
            @if ($canEdit)
                <div class="space-y-2 border-t border-gray-100 pt-2">
                    <label for="linkType" class="block text-xs text-gray-600">{{ __('documentation::docs.pages.link_kind') }}</label>
                    <select id="linkType" wire:model.live="linkType" class="block w-full">
                        <option value="">—</option>
                        @foreach ($kindLabels as $type => $label) <option value="{{ $type }}" wire:key="kind-{{ $type }}">{{ $label }}</option> @endforeach
                    </select>
                    @if ($linkType !== '')
                        <label for="linkSearch" class="sr-only">{{ __('documentation::docs.search') }}</label>
                        <input id="linkSearch" type="search" wire:model.live.debounce.400ms="linkSearch" maxlength="200" placeholder="{{ __('documentation::docs.search') }}" class="block w-full">
                        <ul class="space-y-1">
                            @forelse ($found as $record)
                                <li wire:key="found-{{ $record->id }}" class="flex items-center justify-between gap-2">
                                    <span>{{ $record->title }}</span>
                                    <button type="button" wire:click="link('{{ $record->id }}')" class="rounded-md bg-white px-2 py-0.5 text-xs font-semibold ring-1 ring-gray-300">{{ __('documentation::docs.pages.link') }}</button>
                                </li>
                            @empty
                                <li class="text-gray-500">{{ __('documentation::docs.no_results') }}</li>
                            @endforelse
                        </ul>
                    @endif
                    @error('linkSearch') <p class="text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
        </section>

        <section aria-labelledby="files-heading" class="space-y-2 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
            <h2 id="files-heading" class="font-semibold">{{ __('documentation::docs.pages.files') }}</h2>
            <ul class="space-y-1">
                @forelse ($attachments as $attachment)
                    <li wire:key="file-{{ $attachment->id }}" class="flex items-start justify-between gap-2">
                        <a href="{{ route('core.attachments.download', $attachment) }}" class="text-blue-700 underline">{{ $attachment->original_name }}</a>
                        @if ($canEdit) <button type="button" wire:click="removeFile('{{ $attachment->id }}')" wire:confirm="{{ __('documentation::docs.pages.remove_file_confirm') }}" class="text-xs text-red-700 underline">{{ __('documentation::docs.remove') }}</button> @endif
                    </li>
                @empty
                    <li class="text-gray-500">{{ __('documentation::docs.pages.no_files') }}</li>
                @endforelse
            </ul>
            @if ($canEdit)
                <form wire:submit="uploadFiles" class="space-y-2 border-t border-gray-100 pt-2">
                    <input type="file" wire:model="files" multiple class="block w-full text-xs" aria-label="{{ __('documentation::docs.pages.files') }}">
                    @error('files') <p class="text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-white px-3 py-1 text-xs font-semibold ring-1 ring-gray-300">{{ __('documentation::docs.pages.upload') }}</button>
                </form>
            @endif
        </section>
    </aside>

    <x-core::record-history :record="$page" :can-change="auth()->user()->can('update', $page)" />
</div>
