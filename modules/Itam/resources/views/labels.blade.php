<x-core::layouts.base :title="__('itam::labels.title')">
    <main class="mx-auto max-w-[210mm] space-y-4 bg-white p-6 print:max-w-none print:p-0">
        <div class="space-y-2 text-sm print:hidden">
            <div class="flex items-center justify-between gap-4">
                <a href="{{ route('itam.assets.index') }}" class="text-gray-500 underline">{{ __('itam::assets.ui.back') }}</a>
                <button type="button" data-print class="rounded-md bg-gray-900 px-4 py-2 font-semibold text-white">{{ __('itam::labels.print') }}</button>
            </div>
            <p class="text-gray-600">{{ trans_choice('itam::labels.intro', $labels->count(), ['count' => $labels->count()]) }}</p>
            @if ($truncated)
                <p class="rounded-md bg-amber-50 p-2 text-amber-800">{{ __('itam::labels.truncated', ['max' => \Modules\Itam\Http\Controllers\AssetLabelsController::MAX]) }}</p>
            @endif
        </div>

        <div class="grid grid-cols-3 gap-[2mm]">
            @foreach ($labels as $label)
                <div class="flex h-[35mm] break-inside-avoid items-center gap-[2mm] overflow-hidden rounded border border-gray-300 p-[2mm]">
                    <img src="{{ $label['qr'] }}" alt="" class="h-[30mm] w-[30mm] shrink-0">
                    <div class="min-w-0 text-[8pt] leading-tight">
                        <p class="truncate text-gray-600">{{ $organization }}</p>
                        <p class="font-mono text-[11pt] font-bold">{{ $label['tag'] }}</p>
                        <p class="line-clamp-3">{{ $label['name'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </main>
</x-core::layouts.base>
