@props(['ticket'])

@if ($ticket->service_id !== null || $ticket->serviceAnswers() !== [])
    <section class="rounded-lg bg-white p-4 ring-1 ring-gray-200" aria-labelledby="service-answers-heading">
        <h2 id="service-answers-heading" class="mb-2 text-sm font-semibold">{{ __('ticketing::catalog.answers', ['service' => $ticket->service?->name ?? $ticket->title]) }}</h2>
        <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
            @foreach ($ticket->serviceAnswers() as $answer)
                <dt class="text-gray-500">{{ $answer['label'] }}</dt>
                <dd class="whitespace-pre-line sm:col-span-2">
                    @if ($answer['type'] === 'checkbox')
                        {{ $answer['value'] ? __('core::forms.yes') : __('core::forms.no') }}
                    @else
                        {{ $answer['value'] ?? '—' }}
                    @endif
                </dd>
            @endforeach
        </dl>
    </section>
@endif
