<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('ticketing::catalog.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('ticketing::catalog.intro') }}</p>
        </div>
        <label class="text-sm">
            <span class="sr-only">{{ __('ticketing::ui.search') }}</span>
            <input type="search" wire:model.live.debounce.300ms="search" maxlength="100" placeholder="{{ __('ticketing::catalog.search_placeholder') }}" class="rounded-md border-gray-300">
        </label>
    </div>

    <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($services as $service)
            <li wire:key="service-{{ $service->id }}" class="flex flex-col rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <h2 class="font-semibold">{{ $service->name }}</h2>
                @if ($service->category)
                    <p class="text-xs text-gray-500">{{ $service->category->name }}</p>
                @endif
                <p class="mt-2 flex-1 whitespace-pre-line text-sm text-gray-700">{{ $service->description }}</p>
                <a href="{{ route('ticketing.catalog.request', $service) }}" class="mt-4 self-start rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-gray-700">{{ __('ticketing::catalog.request') }}</a>
            </li>
        @empty
            <li class="text-sm text-gray-500">{{ __('ticketing::catalog.empty') }}</li>
        @endforelse
    </ul>

    <p class="text-sm text-gray-600">
        {{ __('ticketing::catalog.not_listed') }}
        <a href="{{ route('ticketing.portal.create') }}" class="underline">{{ __('ticketing::catalog.open_ticket') }}</a>
    </p>
</div>
