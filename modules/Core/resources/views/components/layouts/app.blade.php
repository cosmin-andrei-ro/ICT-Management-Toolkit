@props(['title' => null])

<x-core::layouts.base :title="$title">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
            <div class="flex items-center gap-6">
                <a href="{{ route('home') }}" class="font-semibold">{{ config('app.name') }}</a>

                @auth
                    <nav class="flex items-center gap-4 text-sm" aria-label="{{ __('core::layout.main_menu') }}">
                        @foreach (app(\Modules\Core\Http\Navigation\Navigation::class)->for(auth()->user()) as $item)
                            <a href="{{ $item['url'] }}" @class(['text-gray-600 hover:text-gray-900', 'font-semibold text-gray-900' => request()->routeIs($item['active'])])>{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                @endauth
            </div>

            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('manual.index') }}" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-gray-600 ring-1 ring-gray-300 hover:bg-gray-50 hover:text-gray-900">
                    <span aria-hidden="true">?</span> {{ __('core::manual.title') }}
                </a>
                @auth
                    <a href="{{ route('account.security') }}" class="text-gray-600 hover:text-gray-900" title="{{ __('core::security.title') }}">{{ auth()->user()->name }}</a>
                @endauth

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-gray-600 underline hover:text-gray-900">{{ __('core::auth.logout') }}</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8">
        {{ $slot }}
    </main>
</x-core::layouts.base>
