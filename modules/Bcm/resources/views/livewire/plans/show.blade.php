<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            @can('viewAny', \Modules\Bcm\Domain\Models\Plan::class)
                <a href="{{ route('bcm.plans.index') }}" class="text-sm text-gray-500 underline">{{ __('bcm::plans.ui.back') }}</a>
            @else
                <a href="{{ route('bcm.mine') }}" class="text-sm text-gray-500 underline">← {{ __('bcm::plans.mine.title') }}</a>
            @endcan
            <h1 class="text-xl font-semibold">{{ $plan->name }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded bg-gray-100 px-2 py-0.5">{{ $plan->type->label() }}</span>
                @if ($plan->status === 'retired') <span class="rounded bg-gray-100 px-2 py-0.5">{{ __('bcm::plans.plan_statuses.retired') }}</span> @endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('activate', $plan)
                <a href="{{ route('bcm.activations.create', $plan) }}" class="rounded-md bg-red-700 px-3 py-1.5 text-sm font-semibold text-white">{{ __('bcm::activations.ui.new') }}</a>
            @endcan
            @if ($plan->approved_version !== null)
                <a href="{{ route('bcm.plans.sheet', $plan) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::plans.ui.print') }}</a>
            @endif
            @can('edit', $plan)
                @php($open = $versions->first(fn ($version) => $version->status->isOpen()))
                <button type="button" wire:click="startVersion" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __($open ? 'bcm::plans.ui.continue' : ($versions->isEmpty() ? 'bcm::plans.ui.start' : 'bcm::plans.ui.review')) }}</button>
            @endcan
            @can('update', $plan)
                <a href="{{ route('bcm.plans.edit', $plan) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::plans.ui.edit') }}</a>
                <button type="button" wire:click="delete" wire:confirm="{{ __('bcm::plans.ui.delete_confirm') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('bcm::plans.ui.delete') }}</button>
            @endcan
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-3">
        <section aria-labelledby="details-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="details-heading" class="text-sm font-semibold">{{ __('bcm::plans.ui.details') }}</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-xs text-gray-500">{{ __('bcm::plans.fields.owner') }}</dt><dd>{{ $plan->owner?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('bcm::plans.fields.deputy') }}</dt><dd>{{ $plan->deputy?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('bcm::plans.fields.approved_version') }}</dt><dd>{{ $plan->approved_version !== null ? 'v'.$plan->approved_version.' · '.$plan->approved_at?->toDateString() : __('bcm::plans.ui.not_approved') }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('bcm::exercises.fields.last_exercised') }}</dt><dd>{{ $plan->last_exercised_on?->toDateString() ?? __('bcm::exercises.ui.never') }}</dd></div>
                @if ($exerciseDueOn) <div><dt class="text-xs text-gray-500">{{ __('bcm::coverage.next_exercise') }}</dt><dd @class(['font-medium text-amber-700' => $exerciseDueOn->isPast()])>{{ $exerciseDueOn->toDateString() }}</dd></div> @endif
                <div><dt class="text-xs text-gray-500">{{ __('bcm::plans.fields.next_review') }}</dt><dd @class(['font-medium text-amber-700' => $plan->next_review_on?->isPast()])>{{ $plan->next_review_on?->toDateString() ?? '—' }}</dd></div>
                @if ($plan->description) <div><dt class="text-xs text-gray-500">{{ __('bcm::plans.fields.description') }}</dt><dd class="whitespace-pre-line">{{ $plan->description }}</dd></div> @endif
            </dl>
            @if ($plan->approved_version !== null) <p class="border-t border-gray-100 pt-2 text-xs text-gray-500">{{ __('bcm::plans.ui.offline') }}</p> @endif
        </section>

        <section aria-labelledby="versions-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200 lg:col-span-2">
            <h2 id="versions-heading" class="text-sm font-semibold">{{ __('bcm::plans.ui.versions') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($versions as $version)
                    <li wire:key="version-{{ $version->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <a href="{{ route('bcm.versions.show', $version) }}" class="text-blue-700 underline">{{ __('bcm::plans.ui.version', ['version' => $version->version]) }}</a>
                        <span class="flex items-center gap-2 text-xs text-gray-500">
                            <x-bcm::version-status :status="$version->status" />
                            {{ $version->decided_at?->toDateString() ?? $version->updated_at?->toDateString() }}
                            @if ($version->decider) · {{ $version->decider->name }} @endif
                        </span>
                    </li>
                @empty
                    <li class="py-2 text-gray-500">{{ __('bcm::plans.ui.no_versions') }}</li>
                @endforelse
            </ul>
        </section>
    </div>

    <section aria-labelledby="exercises-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="exercises-heading" class="text-sm font-semibold">{{ __('bcm::exercises.ui.title') }}</h2>
            @can('exercise', $plan)
                <a href="{{ route('bcm.exercises.create', $plan) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::exercises.ui.new') }}</a>
            @endcan
        </div>
        @if ($plan->approved_version === null) <p class="text-xs text-gray-500">{{ __('bcm::exercises.ui.needs_approval') }}</p> @endif
        <ul class="divide-y divide-gray-100 text-sm">
            @forelse ($exercises as $exercise)
                <li wire:key="exercise-{{ $exercise->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <a href="{{ route('bcm.exercises.show', $exercise) }}" class="text-blue-700 underline">{{ $exercise->title }}</a>
                    <span class="flex items-center gap-2 text-xs text-gray-500">
                        {{ $exercise->type->label() }} · {{ $exercise->scheduled_on->toDateString() }}
                        <x-bcm::exercise-status :status="$exercise->status" />
                        @if ($exercise->outcome) <span class="font-medium">{{ $exercise->outcome->label() }}</span> @endif
                    </span>
                </li>
            @empty
                <li class="py-2 text-gray-500">{{ __('bcm::exercises.ui.none') }}</li>
            @endforelse
        </ul>
    </section>

    @if ($activations->isNotEmpty())
        <section aria-labelledby="activations-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="activations-heading" class="text-sm font-semibold">{{ __('bcm::activations.ui.list') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($activations as $activation)
                    <li wire:key="activation-{{ $activation->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <a href="{{ route('bcm.activations.show', $activation) }}" class="text-blue-700 underline">{{ $activation->activated_at->copy()->setTimezone(config()->string('app.display_timezone'))->format('Y-m-d H:i') }} · {{ \Illuminate\Support\Str::limit($activation->reason, 80) }}</a>
                        <span @class(['rounded px-2 py-0.5 text-xs font-medium', 'bg-red-50 text-red-800' => $activation->isOpen(), 'bg-gray-100 text-gray-700' => ! $activation->isOpen()])>{{ __($activation->isOpen() ? 'bcm::activations.open' : 'bcm::activations.closed') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <x-core::record-history :record="$plan" />
</div>
