<div class="mx-auto max-w-3xl space-y-6">
    <header class="space-y-1">
        <h1 class="text-xl font-semibold">{{ __('bcm::plans.mine.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('bcm::plans.mine.intro') }}</p>
    </header>

    <ul class="divide-y divide-gray-100 rounded-lg bg-white ring-1 ring-gray-200">
        @forelse ($plans as $plan)
            <li wire:key="plan-{{ $plan->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                <div>
                    <a href="{{ route('bcm.plans.show', $plan) }}" class="font-medium text-blue-700 underline">{{ $plan->name }}</a>
                    <p class="text-gray-500">{{ $plan->type->label() }}</p>
                </div>
                <div class="text-right text-xs text-gray-500">
                    @if ($open->has($plan->id)) <x-bcm::version-status :status="$open->get($plan->id)->status" /> @endif
                    <p class="mt-1">{{ __('bcm::plans.fields.next_review') }}: {{ $plan->next_review_on?->toDateString() ?? '—' }}</p>
                </div>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-gray-500">{{ __('bcm::plans.mine.empty') }}</li>
        @endforelse
    </ul>
</div>
