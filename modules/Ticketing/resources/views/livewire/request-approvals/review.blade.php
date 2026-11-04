<div class="space-y-4">
    <a href="{{ route('ticketing.request-approvals') }}" class="text-sm text-gray-500 underline">← {{ __('ticketing::catalog.approval.title') }}</a>
    <h1 class="text-xl font-semibold"><span class="font-mono text-gray-500">{{ $this->request->number }}</span> {{ $this->request->title }}</h1>
    <p class="text-sm text-gray-500">{{ __('ticketing::catalog.approval.requested_by', ['name' => $this->request->requester?->name ?? '—', 'when' => $this->request->created_at?->diffForHumans()]) }}</p>

    <section class="space-y-3 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
        <p class="whitespace-pre-line">{{ $this->request->description }}</p>
        <x-ticketing::service-answers :ticket="$this->request" />
    </section>

    <form class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200" wire:submit="approve">
        <div>
            <label for="comment" class="block text-sm font-medium">{{ __('ticketing::catalog.approval.comment') }}</label>
            <textarea id="comment" wire:model="comment" rows="3" maxlength="5000" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm"></textarea>
            <p class="text-xs text-gray-500">{{ __('ticketing::catalog.approval.comment_help') }}</p>
            @error('comment') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('ticketing::catalog.approval.approve') }}</button>
            <button type="button" wire:click="reject" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('ticketing::catalog.approval.reject') }}</button>
        </div>
    </form>
</div>
