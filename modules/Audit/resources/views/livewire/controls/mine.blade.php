<div class="space-y-6">
    <header>
        <h1 class="text-xl font-semibold">{{ __('audit::compliance.mine.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('audit::compliance.mine.intro') }}</p>
    </header>
    <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
        @forelse ($controls as $control)
            <li wire:key="control-{{ $control->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                <a href="{{ route('audit.controls.show', $control) }}" class="text-blue-700 underline">{{ $control->code() }} · {{ $control->name }}</a>
                <x-audit::status :status="$control->status" />
            </li>
        @empty
            <li class="py-2 text-gray-500">{{ __('audit::compliance.mine.none') }}</li>
        @endforelse
    </ul>
</div>
