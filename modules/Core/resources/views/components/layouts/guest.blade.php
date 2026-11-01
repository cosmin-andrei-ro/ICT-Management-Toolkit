@props(['title' => null])

<x-core::layouts.base :title="$title">
    <main class="flex min-h-[80vh] items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <h1 class="mb-8 text-center text-2xl font-semibold tracking-tight">{{ config('app.name') }}</h1>

            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                {{ $slot }}
            </div>
        </div>
    </main>
</x-core::layouts.base>
