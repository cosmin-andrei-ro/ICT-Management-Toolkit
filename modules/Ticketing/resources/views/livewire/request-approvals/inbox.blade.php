<div class="space-y-4">
    <h1 class="text-xl font-semibold">{{ __('ticketing::catalog.approval.title') }}</h1>
    <p class="text-sm text-gray-500">{{ __('ticketing::catalog.approval.intro') }}</p>

    @if (session('status'))
        <p class="rounded-md bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
    @endif

    <ul class="divide-y divide-gray-100 rounded-lg bg-white ring-1 ring-gray-200">
        @forelse ($requests as $request)
            <li wire:key="request-{{ $request->id }}" class="flex items-center justify-between gap-4 p-4 text-sm">
                <a href="{{ route('ticketing.request-approvals.show', $request) }}" class="text-blue-700 underline">{{ $request->number }} {{ $request->title }}</a>
                <span class="text-gray-500">{{ $request->requester?->name }} · {{ $request->created_at?->diffForHumans() }}</span>
            </li>
        @empty
            <li class="p-4 text-sm text-gray-500">{{ __('ticketing::catalog.approval.nothing') }}</li>
        @endforelse
    </ul>

    {{ $requests->links() }}
</div>
