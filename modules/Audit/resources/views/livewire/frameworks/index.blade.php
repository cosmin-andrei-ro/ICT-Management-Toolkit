<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('audit::compliance.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('audit::compliance.ui.intro') }}</p>
        </div>
        <a href="{{ route('audit.controls.index') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::compliance.ui.controls_link', ['count' => $controls]) }}</a>
    </div>

    @if ($controls === 0 && $frameworks->isNotEmpty())
        <p class="rounded-md bg-amber-50 p-3 text-sm text-amber-900" role="status">{{ __('audit::compliance.ui.no_controls_yet') }}</p>
    @endif

    <ul class="grid gap-4 md:grid-cols-2">
        @forelse ($frameworks as $framework)
            @php($figures = $coverage[$framework->id])
            <li wire:key="framework-{{ $framework->id }}" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <div>
                    <a href="{{ route('audit.frameworks.show', $framework) }}" class="font-semibold text-blue-700 underline">{{ $framework->name }}</a>
                    @if ($framework->version) <span class="text-sm text-gray-500">{{ $framework->version }}</span> @endif
                    <p class="text-xs text-gray-500">{{ __('audit::compliance.ui.requirements', ['count' => $figures['total']]) }}@if ($figures['states']['undecided'] > 0) · {{ __('audit::compliance.ui.undecided', ['count' => $figures['states']['undecided']]) }}@endif</p>
                </div>
                @if ($figures['implemented_percent'] !== null)
                    <div>
                        <div class="h-2 w-full overflow-hidden rounded bg-gray-100" role="img" aria-label="{{ __('audit::compliance.ui.met', ['percent' => $figures['implemented_percent']]) }}">
                            <div class="h-2 bg-green-600" style="width: {{ $figures['implemented_percent'] }}%"></div>
                        </div>
                        <p class="mt-1 text-sm">{{ __('audit::compliance.ui.met', ['percent' => $figures['implemented_percent']]) }}</p>
                    </div>
                @else
                    <p class="text-sm text-gray-500">{{ __('audit::compliance.ui.not_measured') }}</p>
                @endif
                <ul class="flex flex-wrap gap-2 text-xs">
                    @foreach ($figures['states'] as $state => $count)
                        @if ($count > 0)
                            <li wire:key="framework-{{ $framework->id }}-{{ $state }}"><a href="{{ route('audit.frameworks.show', ['framework' => $framework, 'state' => $state]) }}"><x-audit::state :state="\Modules\Audit\Domain\Enums\RequirementState::from($state)" /> {{ $count }}</a></li>
                        @endif
                    @endforeach
                </ul>
            </li>
        @empty
            <li class="text-sm text-gray-500">{{ __('audit::compliance.ui.none') }}</li>
        @endforelse
    </ul>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="archived"> {{ __('audit::compliance.ui.show_archived') }}</label>

    @can('create', \Modules\Audit\Domain\Models\Framework::class)
        <section aria-labelledby="add-heading" class="space-y-4 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="add-heading" class="text-sm font-semibold">{{ __('audit::compliance.ui.add_title') }}</h2>
            <div class="grid gap-6 md:grid-cols-2">
                <form wire:submit="addFromCatalog" class="space-y-2">
                    <h3 class="text-sm font-medium">{{ __('audit::compliance.ui.add_catalog') }}</h3>
                    @if ($catalog === [])
                        <p class="text-sm text-gray-500">{{ __('audit::compliance.ui.all_added') }}</p>
                    @else
                        <label for="catalogKey" class="sr-only">{{ __('audit::compliance.fields.framework') }}</label>
                        <div class="flex flex-wrap gap-2">
                            <select id="catalogKey" wire:model="catalogKey" class="block">
                                <option value="">—</option>
                                @foreach ($catalog as $key => $name) <option value="{{ $key }}" wire:key="catalog-{{ $key }}">{{ $name }}</option> @endforeach
                            </select>
                            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('audit::compliance.ui.add_catalog_button') }}</button>
                        </div>
                        @error('catalogKey') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @endif
                    <p class="text-xs text-gray-500">{{ __('audit::compliance.ui.add_catalog_help') }}</p>
                </form>
                <form wire:submit="addCustom" class="space-y-2" novalidate>
                    <h3 class="text-sm font-medium">{{ __('audit::compliance.ui.add_custom') }}</h3>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label for="customName" class="block text-xs text-gray-600">{{ __('audit::compliance.fields.framework') }}</label>
                            <input id="customName" type="text" wire:model="customName" maxlength="255" class="mt-1 block w-full">
                            @error('customName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="customVersion" class="block text-xs text-gray-600">{{ __('audit::compliance.fields.version') }}</label>
                            <input id="customVersion" type="text" wire:model="customVersion" maxlength="64" class="mt-1 block w-full">
                        </div>
                    </div>
                    <div>
                        <label for="customDescription" class="block text-xs text-gray-600">{{ __('audit::compliance.fields.description') }}</label>
                        <textarea id="customDescription" wire:model="customDescription" rows="2" maxlength="10000" class="mt-1 block w-full"></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="customSoa"> {{ __('audit::compliance.fields.has_soa') }}</label>
                    <p class="text-xs text-gray-500">{{ __('audit::compliance.ui.add_custom_help') }}</p>
                    <button type="submit" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::compliance.ui.add_custom_button') }}</button>
                </form>
            </div>
        </section>
    @endcan
</div>
