@props(['title' => null])

<x-core::layouts.base :title="$title">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
            <a href="{{ route('home') }}" class="font-semibold">{{ config('app.name') }}</a>

            <div class="flex items-center gap-4 text-sm">
                <span class="text-gray-600">{{ auth()->user()?->name }}</span>

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
