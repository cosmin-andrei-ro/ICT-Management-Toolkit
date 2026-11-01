<div class="mx-auto max-w-3xl space-y-6">
    <header class="space-y-2">
        <a href="{{ route('ticketing.portal.index') }}" class="text-sm text-gray-500 underline">{{ __('ticketing::ui.portal.back') }}</a>
        <h1 class="text-xl font-semibold"><span class="font-mono text-gray-500">{{ $ticket->number }}</span> {{ $ticket->title }}</h1>
        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
            <x-ticketing::badges :ticket="$ticket" />
            <span>{{ $ticket->type->label() }}</span>
            @if ($ticket->assignee)
                <span>· {{ __('ticketing::ui.handled_by', ['name' => $ticket->assignee->name]) }}</span>
            @endif
        </div>
    </header>

    <section class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <p class="whitespace-pre-line text-sm text-gray-800">{{ $ticket->description }}</p>
    </section>

    <x-ticketing::service-answers :ticket="$ticket" />

    @error('action') <p class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p> @enderror

    @if ($ticket->status->value === 'resolved')
        <section class="space-y-3 rounded-lg bg-green-50 p-4 ring-1 ring-green-200">
            <p class="text-sm text-green-900">{{ __('ticketing::ui.portal.resolved_prompt') }}</p>
            @can('close', $ticket)
                <button type="button" wire:click="close" class="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white">{{ __('ticketing::ui.portal.confirm_resolution') }}</button>
            @endcan
            @can('reopen', $ticket)
                <form wire:submit="reopen" class="space-y-2">
                    <label for="reopen-reason" class="block text-sm font-medium">{{ __('ticketing::ui.portal.reopen_reason') }}</label>
                    <textarea id="reopen-reason" wire:model="reopenReason" rows="2" class="block w-full rounded-md border-gray-300"></textarea>
                    @error('reopenReason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('ticketing::ui.reopen') }}</button>
                </form>
            @endcan
        </section>
    @endif

    <x-ticketing::conversation :comments="$comments" :attachments="$attachments" />

    @can('comment', $ticket)
        <form wire:submit="sendReply" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <label for="reply" class="block text-sm font-medium">{{ __('ticketing::ui.reply') }}</label>
            <textarea id="reply" wire:model="reply" rows="4" class="block w-full rounded-md border-gray-300"></textarea>
            @error('reply') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <input type="file" wire:model="files" multiple class="block w-full text-sm" aria-label="{{ __('ticketing::ui.fields.attachments') }}">
            @error('files.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex items-center justify-between">
                <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('ticketing::ui.send') }}</button>
                @can('cancel', $ticket)
                    <button type="button" wire:click="cancel" wire:confirm="{{ __('ticketing::ui.portal.cancel_confirm') }}" class="text-sm text-red-700 underline">{{ __('ticketing::ui.portal.cancel') }}</button>
                @endcan
            </div>
        </form>
    @endcan
</div>
