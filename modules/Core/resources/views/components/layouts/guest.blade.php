@props(['title' => null])

<x-core::layouts.base :title="$title">
    <main class="flex min-h-[80vh] items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <h1 class="mb-8 text-center text-2xl font-semibold tracking-tight">{{ config('app.name') }}</h1>

            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                {{ $slot }}
            </div>

            <p class="mt-4 text-center text-sm">
                <a href="{{ route('manual.show', 'getting-started') }}" class="text-gray-600 underline hover:text-gray-900">{{ __('core::manual.help_signing_in') }}</a>
            </p>
        </div>
    </main>
</x-core::layouts.base>
