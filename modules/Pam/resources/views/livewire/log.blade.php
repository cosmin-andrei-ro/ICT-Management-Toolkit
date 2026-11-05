<div class="space-y-6">
    <header class="space-y-1">
        <a href="{{ route('pam.accounts.index') }}" class="text-sm text-gray-500 underline">← {{ __('pam::accounts.title') }}</a>
        <h1 class="text-xl font-semibold">{{ __('pam::accounts.log.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('pam::accounts.log.intro') }}</p>
    </header>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs text-gray-500">
                <tr>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('pam::accounts.log.when') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('pam::accounts.log.who') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('pam::accounts.log.account') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('pam::accounts.log.reason') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($entries as $entry)
                    <tr wire:key="entry-{{ $entry->id }}">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $entry->created_at?->timezone($timezone)->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-2">{{ $entry->user?->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $entry->auditable?->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $entry->new_values['reason'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-3 text-gray-500">{{ __('pam::accounts.log.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $entries->links() }}
</div>
