<div class="mx-auto max-w-3xl space-y-8">
    <div>
        <h1 class="text-xl font-semibold">{{ __('core::api_tokens.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('core::api_tokens.intro') }}</p>
    </div>

    @if ($plainToken !== null)
        <section class="space-y-2 rounded-lg bg-amber-50 p-5 ring-1 ring-amber-200" aria-live="polite">
            <h2 class="font-semibold text-amber-900">{{ __('core::api_tokens.copy_now') }}</h2>
            <p class="text-sm text-amber-900">{{ __('core::api_tokens.copy_help') }}</p>
            <code class="block break-all rounded-md bg-white p-3 font-mono text-sm ring-1 ring-amber-200" data-testid="plain-token">{{ $plainToken }}</code>
            <button type="button" wire:click="forget" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('core::api_tokens.copied') }}</button>
        </section>
    @endif

    <section class="space-y-4 rounded-lg bg-white p-5 ring-1 ring-gray-200">
        <h2 class="font-semibold">{{ __('core::api_tokens.new') }}</h2>
        <form wire:submit="create" class="space-y-4">
            <div>
                <label for="token-name" class="block text-sm font-medium">{{ __('core::api_tokens.fields.name') }}</label>
                <input wire:model="name" id="token-name" type="text" maxlength="100" placeholder="{{ __('core::api_tokens.fields.name_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <fieldset>
                <legend class="text-sm font-medium">{{ __('core::api_tokens.fields.abilities') }}</legend>
                <p class="text-xs text-gray-500">{{ __('core::api_tokens.fields.abilities_help') }}</p>
                <div class="mt-2 space-y-1">
                    @foreach ($available as $ability => $description)
                        <label wire:key="ability-{{ $ability }}" class="flex items-start gap-2 text-sm">
                            <input type="checkbox" wire:model="abilities" value="{{ $ability }}" class="mt-0.5 rounded border-gray-300">
                            <span><span class="font-mono">{{ $ability }}</span> <span class="text-gray-600">· {{ __($description) }}</span></span>
                        </label>
                    @endforeach
                </div>
                @error('abilities') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </fieldset>
            <div class="flex flex-wrap gap-4">
                <div>
                    <label for="token-days" class="block text-sm font-medium">{{ __('core::api_tokens.fields.days') }}</label>
                    <input wire:model="days" id="token-days" type="number" min="1" max="365" class="mt-1 block w-28 rounded-md border-gray-300 text-sm">
                    @error('days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="token-code" class="block text-sm font-medium">{{ __('core::api_tokens.fields.code') }}</label>
                    <input wire:model="code" id="token-code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="32" class="mt-1 block w-40 rounded-md border-gray-300 text-sm">
                    @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            @unless ($twoFactor)
                <p class="rounded-md bg-amber-50 p-3 text-sm text-amber-800">{{ __('core::api_tokens.needs_two_factor') }} <a href="{{ route('account.security') }}" class="underline">{{ __('core::security.title') }}</a></p>
            @endunless
            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('core::api_tokens.create') }}</button>
        </form>
    </section>

    <section class="space-y-3 rounded-lg bg-white p-5 ring-1 ring-gray-200">
        <h2 class="font-semibold">{{ __('core::api_tokens.yours') }}</h2>
        @if ($tokens->isEmpty())
            <p class="text-sm text-gray-500">{{ __('core::api_tokens.none') }}</p>
        @else
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($tokens as $token)
                    <li wire:key="token-{{ $token->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span>
                            <span class="font-medium">{{ $token->name }}</span>
                            <span class="block text-xs text-gray-500">
                                {{ implode(', ', $token->abilities ?? []) }}
                                · {{ $token->last_used_at ? __('core::api_tokens.last_used', ['date' => $token->last_used_at->timezone($timezone)->format('Y-m-d H:i')]) : __('core::api_tokens.never_used') }}
                                · {{ $token->expires_at ? __('core::api_tokens.expires', ['date' => $token->expires_at->timezone($timezone)->format('Y-m-d')]) : '' }}
                            </span>
                        </span>
                        <button type="button" wire:click="revoke('{{ $token->id }}')" wire:confirm="{{ __('core::api_tokens.revoke_confirm') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('core::api_tokens.revoke') }}</button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <p class="text-sm text-gray-600">{{ __('core::api_tokens.docs') }}</p>
</div>
