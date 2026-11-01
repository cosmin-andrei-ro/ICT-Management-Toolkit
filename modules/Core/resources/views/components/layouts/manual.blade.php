@props(['title' => null, 'contents' => [], 'current' => null])

<x-core::layouts.base :title="$title ? $title.' · '.__('core::manual.title') : __('core::manual.title')">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
            <a href="{{ route('manual.index') }}" class="font-semibold">{{ config('app.name') }} · {{ __('core::manual.title') }}</a>
            @auth
                <a href="{{ route('home') }}" class="text-sm text-gray-600 underline hover:text-gray-900">{{ __('core::manual.back_to_app') }}</a>
            @else
                <a href="{{ route('login') }}" class="text-sm text-gray-600 underline hover:text-gray-900">{{ __('core::auth.login.title') }}</a>
            @endauth
        </div>
    </header>

    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-8 lg:grid-cols-4">
        <nav class="text-sm lg:col-span-1" aria-label="{{ __('core::manual.contents') }}">
            @foreach ($contents as $audience => $pages)
                <h2 class="mb-1 mt-4 text-xs font-semibold uppercase text-gray-500 first:mt-0">{{ \Modules\Core\Domain\Manual\ManualAudience::from($audience)->label() }}</h2>
                <ul class="space-y-1">
                    @foreach ($pages as $item)
                        <li>
                            <a href="{{ route('manual.show', $item->slug) }}" @class(['block rounded px-2 py-1 hover:bg-gray-100', 'bg-gray-100 font-semibold' => $current === $item->slug]) @if ($current === $item->slug) aria-current="page" @endif>{{ $item->title }}</a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <main class="lg:col-span-3">
            {{ $slot }}
        </main>
    </div>
</x-core::layouts.base>
