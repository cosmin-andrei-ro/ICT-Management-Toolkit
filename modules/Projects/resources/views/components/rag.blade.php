@props(['rag', 'label' => null])
{{-- Colour with its word, never colour alone. --}}
<span @class([
    'inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium ring-1',
    'bg-green-50 text-green-800 ring-green-200' => $rag === \Modules\Projects\Domain\Enums\Rag::Green,
    'bg-amber-50 text-amber-900 ring-amber-300' => $rag === \Modules\Projects\Domain\Enums\Rag::Amber,
    'bg-red-50 text-red-800 ring-red-300' => $rag === \Modules\Projects\Domain\Enums\Rag::Red,
])>@if ($label){{ $label }}: @endif{{ $rag->label() }}</span>
