<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('pam.accounts.index') }}" class="text-sm text-gray-500 underline">← {{ __('pam::accounts.title') }}</a>
            <h1 class="text-xl font-semibold">{{ $account->name }}</h1>
            <p class="text-sm text-gray-600">{{ $account->type->label() }} · <span class="font-mono">{{ $account->username }}</span></p>
        </div>
        <div class="flex gap-2">
            @can('update', $account)
                <a href="{{ route('pam.accounts.edit', $account) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('pam::accounts.ui.edit') }}</a>
            @endcan
            @can('delete', $account)
            @endcan
        </div>
    </header>

    @if (! $configured)
        <p class="rounded-md bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ __('pam::accounts.errors.not_configured') }}</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="details-heading" class="space-y-2 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
            <h2 id="details-heading" class="font-semibold">{{ __('pam::accounts.ui.details') }}</h2>
            <dl class="space-y-2">
                <div><dt class="text-xs text-gray-500">{{ __('pam::accounts.fields.owner') }}</dt><dd>{{ $account->owner?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('pam::accounts.fields.item') }}</dt><dd>@if ($item)<a href="{{ $item->url }}" class="text-blue-700 underline">{{ $item->name }}</a>@else — @endif</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('pam::accounts.fields.application') }}</dt><dd>@if ($application)<a href="{{ $application->url }}" class="text-blue-700 underline">{{ $application->name }}</a>@else — @endif</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('pam::accounts.fields.secret_changed') }}</dt><dd>{{ $account->secret_changed_at?->toDateString() ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('pam::accounts.fields.rotation_due') }}</dt><dd @class(['font-medium text-amber-700' => $account->rotationDueOn()?->isPast()])>{{ $account->rotationDueOn()?->toDateString() ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('pam::accounts.fields.review_due') }}</dt><dd @class(['font-medium text-amber-700' => $account->reviewDueOn()?->isPast()])>{{ $account->reviewDueOn()?->toDateString() ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('pam::accounts.fields.requires_approval') }}</dt><dd>{{ $account->requires_approval ? __('pam::accounts.requests.yes_for', ['minutes' => $account->access_minutes]) : __('pam::accounts.requests.no') }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('pam::accounts.fields.description') }}</dt><dd class="whitespace-pre-line">{{ $account->description ?? '—' }}</dd></div>
            </dl>
        </section>

        <section aria-labelledby="secret-heading" class="space-y-3 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
            <h2 id="secret-heading" class="font-semibold">{{ __('pam::accounts.ui.secret') }}</h2>

            @if ($revealed !== null)
                <div wire:poll.{{ $revealSeconds }}s.keep-alive="hide" class="space-y-2">
                    <p class="break-all rounded-md bg-gray-50 p-2 font-mono">{{ $revealed }}</p>
                    <p class="text-xs text-gray-500">{{ __('pam::accounts.ui.revealed_help', ['seconds' => $revealSeconds]) }}</p>
                    <button type="button" wire:click="hide" class="rounded-md bg-white px-3 py-1.5 font-semibold ring-1 ring-gray-300">{{ __('pam::accounts.ui.hide') }}</button>
                </div>
            @elseif (! $account->hasSecret())
                <p class="text-gray-500">{{ __('pam::accounts.ui.no_secret') }}</p>
            @elsecan('reveal', $account)
                @php($active = $myRequest?->isActive() === true)
                @if ($account->requires_approval && ! $active)
                    @if ($myRequest?->status === \Modules\Pam\Domain\Enums\RequestStatus::Pending)
                        <p class="text-gray-600">{{ __('pam::accounts.requests.waiting') }}</p>
                    @else
                        @if ($myRequest?->status === \Modules\Pam\Domain\Enums\RequestStatus::Rejected)
                            <p class="text-red-700">{{ __('pam::accounts.requests.rejected_note', ['comment' => $myRequest->decision_comment ?? '']) }}</p>
                        @elseif ($myRequest !== null)
                            <p class="text-gray-600">{{ __('pam::accounts.requests.expired_note') }}</p>
                        @endif
                        <form wire:submit="askAccess" class="space-y-2">
                            <p class="text-xs text-gray-500">{{ __('pam::accounts.requests.needs_approval', ['minutes' => $account->access_minutes]) }}</p>
                            <div>
                                <label for="reason" class="block font-medium">{{ __('pam::accounts.ui.reason') }}</label>
                                <input id="reason" type="text" wire:model="reason" maxlength="500" class="mt-1 block w-full">
                                @error('reason') <p class="mt-1 text-red-700">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 font-semibold text-white">{{ __('pam::accounts.requests.ask') }}</button>
                        </form>
                    @endif
                @else
                    <form wire:submit="reveal" class="space-y-2">
                        <p class="text-xs text-gray-500">{{ __('pam::accounts.ui.reveal_help') }}</p>
                        @if ($active)
                            <p class="text-xs text-green-800">{{ __('pam::accounts.requests.approved_until', ['time' => $myRequest->expires_at?->timezone(config('app.display_timezone'))->format('H:i')]) }}</p>
                        @else
                            <div>
                                <label for="reason" class="block font-medium">{{ __('pam::accounts.ui.reason') }}</label>
                                <input id="reason" type="text" wire:model="reason" maxlength="500" class="mt-1 block w-full">
                                @error('reason') <p class="mt-1 text-red-700">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <div>
                            <label for="code" class="block font-medium">{{ __('pam::accounts.ui.code') }}</label>
                            <input id="code" type="text" wire:model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="32" class="mt-1 block w-40">
                            @error('code') <p class="mt-1 text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 font-semibold text-white">{{ __('pam::accounts.ui.reveal') }}</button>
                    </form>
                @endif
            @else
                <p class="text-gray-500">{{ __('pam::accounts.ui.not_granted') }}</p>
            @endif

            @can('update', $account)
                <form wire:submit="changeSecret" class="space-y-2 border-t border-gray-100 pt-3">
                    <label for="newSecret" class="block font-medium">{{ __($account->hasSecret() ? 'pam::accounts.ui.change_secret' : 'pam::accounts.ui.set_secret') }}</label>
                    <textarea id="newSecret" wire:model="newSecret" rows="2" maxlength="8192" autocomplete="off" spellcheck="false" class="block w-full font-mono"></textarea>
                    <p class="text-xs text-gray-500">{{ __('pam::accounts.ui.change_help') }}</p>
                    @error('newSecret') <p class="text-red-700">{{ $message }}</p> @enderror
                    <button type="submit" class="rounded-md bg-white px-3 py-1.5 font-semibold ring-1 ring-gray-300">{{ __('pam::accounts.ui.save') }}</button>
                </form>
            @endcan
        </section>
    </div>

    @can('update', $account)
        <section aria-labelledby="code-heading" class="rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
            <h2 id="code-heading" class="sr-only">{{ __('pam::accounts.ui.action_code') }}</h2>
            <div>
                        <label for="actionCode" class="block font-medium">{{ __('pam::accounts.ui.action_code') }}</label>
                        <input id="actionCode" type="text" wire:model="actionCode" inputmode="numeric" autocomplete="one-time-code" maxlength="32" class="mt-1 block w-40">
                        <p class="mt-1 text-xs text-gray-500">{{ __('pam::accounts.ui.action_code_help') }}</p>
                        @error('actionCode') <p class="mt-1 text-red-700">{{ $message }}</p> @enderror
                    </div>
        </section>

        <section aria-labelledby="grants-heading" class="space-y-3 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
            <h2 id="grants-heading" class="font-semibold">{{ __('pam::accounts.ui.grants') }}</h2>
            <p class="text-xs text-gray-500">{{ __('pam::accounts.ui.grants_help') }}</p>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-gray-600">{{ $account->reviewed_at ? __('pam::accounts.ui.last_reviewed', ['date' => $account->reviewed_at->toDateString()]) : __('pam::accounts.ui.never_reviewed') }}</p>
                <button type="button" wire:click="confirmReview" class="rounded-md bg-white px-3 py-1.5 font-semibold ring-1 ring-gray-300">{{ __('pam::accounts.ui.confirm_review') }}</button>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse ($account->grants as $grant)
                    <li wire:key="grant-{{ $grant->id }}" class="flex items-center justify-between gap-2 py-1.5">
                        <span>{{ $grant->user->name }}</span>
                        <button type="button" wire:click="revoke('{{ $grant->id }}')" class="text-xs text-red-700 underline">{{ __('pam::accounts.ui.revoke') }}</button>
                    </li>
                @empty
                    <li class="py-1.5 text-gray-500">{{ __('pam::accounts.ui.no_grants') }}</li>
                @endforelse
            </ul>
            @if ($candidates !== [])
                <form wire:submit="grant" class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="grantUserId" class="block text-xs text-gray-600">{{ __('pam::accounts.ui.grant_to') }}</label>
                        <select id="grantUserId" wire:model="grantUserId" class="mt-1 block">
                            <option value="">—</option>
                            @foreach ($candidates as $id => $person) <option value="{{ $id }}">{{ $person }}</option> @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-md bg-white px-3 py-1.5 font-semibold ring-1 ring-gray-300">{{ __('pam::accounts.ui.grant') }}</button>
                </form>
                @error('grantUserId') <p class="text-red-700">{{ $message }}</p> @enderror
            @endif
        </section>
    @endcan

    @can('delete', $account)
        <section aria-labelledby="delete-heading" class="space-y-2 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
            <h2 id="delete-heading" class="font-semibold">{{ __('pam::accounts.ui.delete') }}</h2>
            <p class="text-xs text-gray-500">{{ __('pam::accounts.ui.delete_help') }}</p>
            @cannot('update', $account)
                <div>
                        <label for="actionCode" class="block font-medium">{{ __('pam::accounts.ui.action_code') }}</label>
                        <input id="actionCode" type="text" wire:model="actionCode" inputmode="numeric" autocomplete="one-time-code" maxlength="32" class="mt-1 block w-40">
                        <p class="mt-1 text-xs text-gray-500">{{ __('pam::accounts.ui.action_code_help') }}</p>
                        @error('actionCode') <p class="mt-1 text-red-700">{{ $message }}</p> @enderror
                    </div>
            @endcannot
            <button type="button" wire:click="delete" wire:confirm="{{ __('pam::accounts.ui.delete_confirm') }}" class="rounded-md bg-white px-3 py-1.5 font-semibold text-red-700 ring-1 ring-gray-300">{{ __('pam::accounts.ui.delete') }}</button>
        </section>
    @endcan

    <x-core::record-history :record="$account" />
</div>
