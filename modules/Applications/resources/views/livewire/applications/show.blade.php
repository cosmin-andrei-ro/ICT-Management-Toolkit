<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            @can('viewAny', \Modules\Applications\Domain\Models\Application::class)
                <a href="{{ route('applications.index') }}" class="text-sm text-gray-500 underline">{{ __('applications::applications.ui.back') }}</a>
            @else
                <a href="{{ route('applications.mine') }}" class="text-sm text-gray-500 underline">← {{ __('applications::applications.mine.title') }}</a>
            @endcan
            <h1 class="text-xl font-semibold">{{ $application->name }} <span class="text-sm font-normal text-gray-500">{{ $application->code }}</span></h1>
            <p class="text-xs text-gray-500">
                {{ $options->label('application_status', $application->status) ?? __('applications::applications.ui.not_documented') }}
                @if ($application->criticality) · {{ $options->label('business_criticality', $application->criticality) }} @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($current)
                <a href="{{ route('applications.sheets.document', $current) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('applications::document.open') }}</a>
                <a href="{{ route('applications.sheets.show', $current) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('applications::applications.ui.read', ['version' => $current->versionLabel()]) }}</a>
            @endif
            @can('edit', $application)
                <button type="button" wire:click="startSheet" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __($open ? 'applications::applications.ui.continue' : ($current ? 'applications::applications.ui.update' : 'applications::applications.ui.document')) }}</button>
            @endcan
            @can('update', $application)
                <a href="{{ route('applications.edit', $application) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('applications::applications.ui.edit') }}</a>
                <button type="button" wire:click="delete" wire:confirm="{{ __('applications::applications.ui.delete_confirm') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('applications::applications.ui.delete') }}</button>
            @endcan
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-3">
        <section aria-labelledby="identity-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="identity-heading" class="text-sm font-semibold">{{ __('applications::applications.ui.identity') }}</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-xs text-gray-500">{{ __('applications::applications.fields.department_owner') }}</dt><dd>{{ $application->department?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('applications::applications.fields.department_email') }}</dt><dd class="break-all">{{ $application->department_email ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('applications::applications.fields.business_owner') }}</dt><dd>{{ $application->businessOwner?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('applications::applications.fields.technical_owner') }}</dt><dd>{{ $application->technicalOwner?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('applications::applications.fields.deputy') }}</dt><dd>{{ $application->deputy?->name ?? '—' }}</dd></div>
            </dl>
        </section>

        <section aria-labelledby="control-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200 lg:col-span-2">
            <h2 id="control-heading" class="text-sm font-semibold">{{ __('applications::applications.ui.document_control') }}</h2>
            <dl class="grid gap-3 text-sm sm:grid-cols-3">
                <div><dt class="text-xs text-gray-500">{{ __('applications::applications.fields.version') }}</dt><dd>{{ $current ? 'v'.$current->versionLabel() : __('applications::applications.ui.not_documented') }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('applications::applications.fields.last_review') }}</dt><dd>{{ $application->approved_at?->toDateString() ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('applications::applications.fields.next_review') }}</dt><dd @class(['font-medium text-amber-700' => $application->next_review_on?->isPast()])>{{ $application->next_review_on?->toDateString() ?? '—' }}</dd></div>
            </dl>
            @if ($open)
                <p class="rounded-md bg-gray-50 p-2 text-sm">
                    <a href="{{ route('applications.sheets.show', $open) }}" class="text-blue-700 underline">{{ __('applications::applications.ui.open_version') }}</a>
                    <x-applications::sheet-status :status="$open->status" />
                </p>
            @endif

            <h3 class="pt-2 text-sm font-semibold">{{ __('applications::applications.ui.history') }}</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs text-gray-500">
                        <tr>
                            <th scope="col" class="py-1 pr-4 font-medium">{{ __('applications::applications.fields.version') }}</th>
                            <th scope="col" class="py-1 pr-4 font-medium">{{ __('applications::applications.fields.date') }}</th>
                            <th scope="col" class="py-1 pr-4 font-medium">{{ __('applications::applications.fields.author') }}</th>
                            <th scope="col" class="py-1 pr-4 font-medium">{{ __('applications::applications.fields.change') }}</th>
                            <th scope="col" class="py-1 font-medium">{{ __('applications::applications.fields.approvals') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 align-top">
                        @forelse ($history as $version)
                            <tr wire:key="version-{{ $version->id }}">
                                <td class="py-1.5 pr-4"><a href="{{ route('applications.sheets.show', $version) }}" class="text-blue-700 underline">{{ $version->versionLabel() }}</a></td>
                                <td class="py-1.5 pr-4">{{ $version->approved_at?->toDateString() }}</td>
                                <td class="py-1.5 pr-4">{{ $version->submitter?->name ?? $version->author?->name ?? '—' }}</td>
                                <td class="py-1.5 pr-4">{{ $version->change_summary }}</td>
                                <td class="py-1.5 text-xs text-gray-600">
                                    @forelse ($version->approvals as $approval)
                                        <span class="block">{{ $approval->role->label() }}: {{ $approval->user?->name ?? '—' }}, {{ $approval->approved_at->toDateString() }}</span>
                                    @empty
                                        {{ __('applications::applications.ui.minor_logged') }}
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-2 text-gray-500">{{ __('applications::applications.ui.no_history') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @if ($articles !== [])
        <section aria-labelledby="articles-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="articles-heading" class="text-sm font-semibold">{{ __('applications::applications.ui.documentation_pages') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($articles as $article)
                    <li wire:key="article-{{ $article->id }}" class="py-1.5"><a href="{{ $article->url }}" class="text-blue-700 underline">{{ $article->title }}</a> <span class="text-xs text-gray-500">{{ $article->status }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($risks !== null)
        <section aria-labelledby="risks-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="risks-heading" class="text-sm font-semibold">{{ __('applications::applications.ui.risks') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($risks as $risk)
                    <li wire:key="risk-{{ $risk->id }}" class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                        <a href="{{ $risk->url }}" class="text-blue-700 underline">{{ $risk->code }} · {{ $risk->title }}</a>
                        <span class="text-xs text-gray-600">{{ $risk->level ? $risk->level.' · '.$risk->score : __('applications::applications.ui.risk_not_scored') }}</span>
                    </li>
                @empty
                    <li class="py-1.5 text-gray-500">{{ __('applications::applications.ui.no_risks') }}</li>
                @endforelse
            </ul>
        </section>
    @endif

    <x-core::record-history :record="$application" :can-change="auth()->user()->can('update', $application)" />
</div>
