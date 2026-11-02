<x-core::layouts.app :title="__('core::layout.home')">
    <div class="space-y-6">
        <h1 class="text-xl font-semibold">{{ __('core::layout.welcome', ['name' => auth()->user()?->name]) }}</h1>

        @foreach ($panels as $panel)
            @livewire($panel)
        @endforeach
    </div>
</x-core::layouts.app>
