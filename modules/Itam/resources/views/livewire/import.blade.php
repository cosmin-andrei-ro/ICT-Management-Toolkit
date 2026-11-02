<div class="mx-auto max-w-4xl space-y-6">
    <header class="space-y-1">
        <a href="{{ route('itam.assets.index') }}" class="text-sm text-gray-500 underline">{{ __('itam::assets.ui.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __('itam::import.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('itam::import.intro') }}</p>
    </header>

    @if ($imported !== null)
        <p class="rounded-md bg-green-50 p-3 text-sm text-green-800" role="status">{{ trans_choice('itam::import.done', $imported, ['count' => $imported]) }}</p>
    @endif

    <form wire:submit="analyse" class="space-y-4 rounded-lg bg-white p-6 ring-1 ring-gray-200">
        <div class="space-y-2 rounded-md bg-gray-50 p-3 text-xs text-gray-700">
            <p>{{ __('itam::import.columns') }}</p>
            <p class="font-mono">{{ implode(';', $columns) }}</p>
            <p>{{ __('itam::import.column_notes') }}</p>
            <p>{{ __('itam::import.attribute_columns') }}</p>
            <ul class="space-y-0.5">
                @foreach ($categories as $category)
                    <li wire:key="import-category-{{ $category->id }}"><span class="font-mono">{{ $category->key }}</span> ({{ $category->name }}): <span class="font-mono">{{ collect($category->schema()->fields)->pluck('key')->implode(', ') ?: '—' }}</span></li>
                @endforeach
            </ul>
        </div>

        <div>
            <label for="file" class="block text-sm font-medium">{{ __('itam::import.file') }}</label>
            <input id="file" type="file" wire:model="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="mt-1 block text-sm">
            <p class="mt-1 text-xs text-gray-500">{{ __('itam::import.file_help', ['rows' => \Modules\Itam\Http\Livewire\ImportAssets::MAX_ROWS, 'mb' => intdiv(\Modules\Itam\Http\Livewire\ImportAssets::MAX_KILOBYTES, 1024)]) }}</p>
            @error('file') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('itam::import.preview') }}</button>
    </form>

    @if ($preview !== [])
        <section aria-labelledby="preview-heading" class="space-y-3 rounded-lg bg-white p-6 ring-1 ring-gray-200">
            <h2 id="preview-heading" class="font-semibold">{{ __('itam::import.preview_title') }}</h2>
            <p class="text-sm">
                @foreach ($counts as $action => $count)
                    <span wire:key="count-{{ $action }}" class="mr-3">{{ __('itam::import.actions.'.$action) }}: <strong>{{ $count }}</strong></span>
                @endforeach
            </p>

            @if ($errorCount > 0)
                <p class="rounded-md bg-red-50 p-3 text-sm text-red-800" role="alert">{{ __('itam::import.fix_first', ['count' => $errorCount]) }}</p>
            @endif

            <table class="w-full text-sm">
                <thead class="text-left text-xs text-gray-500"><tr><th class="py-1">{{ __('itam::import.line') }}</th><th class="py-1">{{ __('itam::import.row') }}</th><th class="py-1">{{ __('itam::import.result') }}</th></tr></thead>
                <tbody>
                    @foreach ($shown as $row)
                        <tr wire:key="preview-{{ $row['line'] }}" class="border-t border-gray-100 align-top">
                            <td class="py-1 text-gray-500">{{ $row['line'] }}</td>
                            <td class="py-1">{{ $row['label'] }}</td>
                            <td class="py-1">
                                {{ __('itam::import.actions.'.$row['action']) }}
                                @foreach ($row['errors'] as $error)
                                    <p class="text-xs text-red-700">{{ $error }}</p>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($errorCount === 0)
                <button type="button" wire:click="import" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ trans_choice('itam::import.confirm', count($preview), ['count' => count($preview)]) }}</button>
            @endif
        </section>
    @endif
</div>
