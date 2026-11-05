<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('pam::accounts.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('pam::accounts.ui.intro') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('pam.audit.view')
                <a href="{{ route('pam.access-log') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('pam::accounts.log.title') }}</a>
            @endcan
            @can('create', \Modules\Pam\Domain\Models\PrivilegedAccount::class)
                <a href="{{ route('pam.accounts.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('pam::accounts.ui.new') }}</a>
            @endcan
        </div>
    </div>

    @if (! $configured)
        <p class="rounded-md bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ __('pam::accounts.errors.not_configured') }}</p>
    @endif

    <div>
        <label for="search" class="block text-xs text-gray-600">{{ __('pam::accounts.ui.search') }}</label>
        <input id="search" type="search" wire:model.live.debounce.400ms="search" maxlength="200" class="mt-1 block w-64">
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs text-gray-500">
                <tr>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('pam::accounts.fields.name') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('pam::accounts.fields.username') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('pam::accounts.fields.type') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('pam::accounts.fields.owner') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('pam::accounts.fields.secret_changed') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($accounts as $account)
                    <tr wire:key="account-{{ $account->id }}">
                        <td class="px-4 py-2"><a href="{{ route('pam.accounts.show', $account) }}" class="text-blue-700 underline">{{ $account->name }}</a></td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $account->username }}</td>
                        <td class="px-4 py-2">{{ $account->type->label() }}</td>
                        <td class="px-4 py-2">{{ $account->owner?->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $account->secret_changed_at?->toDateString() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-3 text-gray-500">{{ __('pam::accounts.ui.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $accounts->links() }}
</div>
