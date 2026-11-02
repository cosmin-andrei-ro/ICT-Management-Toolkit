@php($duration = fn (?int $minutes): string => \Modules\Core\Domain\Time\Duration::format($minutes))
@php($recovery = $activation->recoveryMinutes())
<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('bcm.plans.show', $plan) }}" class="text-sm text-gray-500 underline print:hidden">← {{ $plan->name }}</a>
            <h1 class="flex flex-wrap items-center gap-2 text-xl font-semibold">
                {{ __('bcm::activations.ui.title', ['plan' => $plan->name]) }}
                <span @class(['rounded px-2 py-0.5 text-xs font-medium', 'bg-red-50 text-red-800' => $activation->isOpen(), 'bg-gray-100 text-gray-700' => ! $activation->isOpen()])>{{ __($activation->isOpen() ? 'bcm::activations.open' : 'bcm::activations.closed') }}</span>
            </h1>
            <p class="text-xs text-gray-500">{{ $plan->type->label() }} · {{ __('bcm::plans.ui.version', ['version' => $activation->version->version]) }}</p>
        </div>
        <div class="flex flex-wrap gap-2 print:hidden">
            <a href="{{ route('bcm.plans.sheet', $plan) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::plans.ui.print') }}</a>
            @unless ($activation->isOpen())
                <button type="button" data-print class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::activations.ui.print') }}</button>
            @endunless
        </div>
    </header>

    <section aria-labelledby="activation-heading" class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="activation-heading" class="sr-only">{{ __('bcm::activations.ui.details') }}</h2>
        <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-xs text-gray-500">{{ __('bcm::activations.fields.activated_at') }}</dt><dd>{{ $activation->activated_at->copy()->setTimezone($timezone)->format('Y-m-d H:i') }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('bcm::activations.fields.activated_by') }}</dt><dd>{{ $activation->activator?->name ?? '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('bcm::exercises.fields.scenario') }}</dt><dd>{{ $activation->scenario ? __('bcm::plans.scenarios.'.$activation->scenario->value) : '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('bcm::activations.fields.restored_at') }}</dt><dd>{{ $activation->restored_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') ?? '—' }}</dd></div>
            <div class="sm:col-span-2 lg:col-span-4"><dt class="text-xs text-gray-500">{{ __('bcm::activations.fields.reason') }}</dt><dd class="whitespace-pre-line">{{ $activation->reason }}</dd></div>
            @if ($activation->closer) <div><dt class="text-xs text-gray-500">{{ __('bcm::activations.fields.closed_by') }}</dt><dd>{{ $activation->closer->name }} · {{ $activation->closed_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i') }}</dd></div> @endif
        </dl>
    </section>

    @if ($recovery !== null)
        <p @class(['rounded-md p-3 text-sm',
            'bg-red-50 text-red-800' => $activation->rto_minutes !== null && $recovery > $activation->rto_minutes,
            'bg-green-50 text-green-800' => $activation->rto_minutes !== null && $recovery <= $activation->rto_minutes,
            'bg-gray-50 text-gray-700' => $activation->rto_minutes === null])>
            {{ __('bcm::activations.ui.recovered_in', ['value' => $duration($recovery)]) }}
            @if ($activation->rto_minutes !== null)
                {{ __($recovery > $activation->rto_minutes ? 'bcm::exercises.ui.over_rto' : 'bcm::exercises.ui.within_rto', ['value' => $duration($activation->rto_minutes)]) }}
            @endif
        </p>
    @endif

    @if ($editable)
        <form wire:submit="save" class="space-y-4 rounded-lg bg-white p-4 ring-1 ring-gray-200 print:hidden" novalidate>
            <div class="max-w-xs">
                <label for="restoredAt" class="block text-sm font-medium">{{ __('bcm::activations.fields.restored_at') }}</label>
                <input id="restoredAt" type="datetime-local" wire:model="restoredAt" class="mt-1 block w-full">
                @error('restoredAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="summary" class="block text-sm font-medium">{{ __('bcm::activations.fields.summary') }}</label>
                <textarea id="summary" wire:model="summary" rows="4" maxlength="10000" class="mt-1 block w-full"></textarea>
            </div>
            <div>
                <label for="lessons" class="block text-sm font-medium">{{ __('bcm::exercises.fields.lessons') }}</label>
                <textarea id="lessons" wire:model="lessons" rows="3" maxlength="10000" class="mt-1 block w-full"></textarea>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::activations.ui.save') }}</button>
                <button type="button" wire:click="close" wire:confirm="{{ __('bcm::activations.ui.close_confirm') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('bcm::activations.ui.close') }}</button>
            </div>
            <p class="text-xs text-gray-500">{{ __('bcm::activations.ui.close_help') }}</p>
        </form>
    @else
        <section aria-labelledby="conclusion-heading" class="space-y-2 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
            <h2 id="conclusion-heading" class="text-sm font-semibold">{{ __('bcm::exercises.ui.conclusion') }}</h2>
            <p><span class="text-xs text-gray-500">{{ __('bcm::activations.fields.summary') }}:</span> <span class="whitespace-pre-line">{{ $activation->summary ?? '—' }}</span></p>
            <p><span class="text-xs text-gray-500">{{ __('bcm::exercises.fields.lessons') }}:</span> <span class="whitespace-pre-line">{{ $activation->lessons ?? '—' }}</span></p>
        </section>
    @endif
</div>
