<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('bcm::plans.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('bcm::plans.ui.intro') }}</p>
        </div>
        @can('create', \Modules\Bcm\Domain\Models\Plan::class)
            <a href="{{ route('bcm.plans.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('bcm::plans.ui.new') }}</a>
        @endcan
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <input type="search" wire:model.live.debounce.400ms="search" maxlength="200" placeholder="{{ __('bcm::plans.ui.search') }}" aria-label="{{ __('bcm::plans.ui.search') }}" class="w-64 rounded-md text-sm">
        <select wire:model.live="type" aria-label="{{ __('bcm::plans.fields.type') }}" class="rounded-md text-sm">
            <option value="">{{ __('bcm::plans.ui.all_types') }}</option>
            @foreach ($types as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="retired" class="rounded border-gray-300"> {{ __('bcm::plans.ui.show_retired') }}</label>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500">
                <tr>
                    <th class="px-3 py-2">{{ __('bcm::plans.fields.name') }}</th>
                    <th class="px-3 py-2">{{ __('bcm::plans.fields.type') }}</th>
                    <th class="px-3 py-2">{{ __('bcm::plans.fields.owner') }}</th>
                    <th class="px-3 py-2">{{ __('bcm::plans.fields.approved_version') }}</th>
                    <th class="px-3 py-2">{{ __('bcm::plans.fields.next_review') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($plans as $plan)
                    <tr wire:key="plan-{{ $plan->id }}" class="border-t border-gray-100">
                        <td class="px-3 py-2">
                            <a href="{{ route('bcm.plans.show', $plan) }}" class="font-medium text-blue-700 underline">{{ $plan->name }}</a>
                            @if ($plan->status === 'retired') <span class="ml-1 text-xs text-gray-500">{{ __('bcm::plans.plan_statuses.retired') }}</span> @endif
                            @if ($open->has($plan->id)) <span class="ml-1"><x-bcm::version-status :status="$open->get($plan->id)->status" /></span> @endif
                        </td>
                        <td class="px-3 py-2">{{ __('bcm::plans.type_short.'.$plan->type->value) }}</td>
                        <td class="px-3 py-2">{{ $plan->owner?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $plan->approved_version !== null ? 'v'.$plan->approved_version : '—' }}</td>
                        <td @class(['px-3 py-2', 'font-medium text-amber-700' => $plan->next_review_on !== null && $plan->next_review_on->isPast()])>{{ $plan->next_review_on?->toDateString() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">{{ __('bcm::plans.ui.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $plans->links() }}
</div>
