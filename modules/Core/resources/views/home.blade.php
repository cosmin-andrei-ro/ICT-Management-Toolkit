<x-core::layouts.app :title="__('core::layout.home')">
    <h1 class="text-xl font-semibold">{{ __('core::layout.welcome', ['name' => auth()->user()?->name]) }}</h1>
</x-core::layouts.app>
