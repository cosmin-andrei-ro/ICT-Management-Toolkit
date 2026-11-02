@props(['title' => null])

<x-core::layouts.base :title="$title">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-3">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <a href="{{ route('home') }}" class="whitespace-nowrap font-semibold">{{ config('app.name') }}</a>

                @auth
                    <nav class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm" aria-label="{{ __('core::layout.main_menu') }}">
                        @foreach (app(\Modules\Core\Http\Navigation\Navigation::class)->for(auth()->user()) as $entry)
                            @if (isset($entry['items']))
                                @php($groupActive = collect($entry['items'])->contains(fn (array $item): bool => request()->routeIs($item['active'])))
                                {{-- Native disclosure: works without script; resources/js/navigation.js only closes it on outside clicks and Escape. --}}
                                <details class="relative" data-nav-menu>
                                    <summary @class(['flex cursor-pointer list-none items-center gap-1 whitespace-nowrap text-gray-600 hover:text-gray-900 [&::-webkit-details-marker]:hidden', 'font-semibold text-gray-900' => $groupActive])>
                                        {{ $entry['label'] }} <span aria-hidden="true" class="text-xs">▾</span>
                                    </summary>
                                    <ul class="absolute left-0 z-30 mt-2 min-w-48 rounded-md bg-white py-1 shadow-lg ring-1 ring-gray-200">
                                        @foreach ($entry['items'] as $item)
                                            <li>
                                                <a href="{{ $item['url'] }}" @if (request()->routeIs($item['active'])) aria-current="page" @endif
                                                   @class(['block whitespace-nowrap px-4 py-2 hover:bg-gray-50', 'font-semibold text-gray-900' => request()->routeIs($item['active']), 'text-gray-700' => ! request()->routeIs($item['active'])])>{{ $item['label'] }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @else
                                <a href="{{ $entry['url'] }}" @if (request()->routeIs($entry['active'])) aria-current="page" @endif
                                   @class(['whitespace-nowrap text-gray-600 hover:text-gray-900', 'font-semibold text-gray-900' => request()->routeIs($entry['active'])])>{{ $entry['label'] }}</a>
                            @endif
                        @endforeach
                    </nav>
                @endauth
            </div>

            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('manual.index') }}" class="inline-flex items-center gap-1 whitespace-nowrap rounded-md px-2 py-1 text-gray-600 ring-1 ring-gray-300 hover:bg-gray-50 hover:text-gray-900">
                    <span aria-hidden="true">?</span> {{ __('core::manual.title') }}
                </a>
                @auth
                    <a href="{{ route('account.security') }}" class="whitespace-nowrap text-gray-600 hover:text-gray-900" title="{{ __('core::security.title') }}">{{ auth()->user()->name }}</a>
                @endauth

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="whitespace-nowrap text-gray-600 underline hover:text-gray-900">{{ __('core::auth.logout') }}</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8">
        {{ $slot }}
    </main>
</x-core::layouts.base>
