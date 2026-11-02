<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('projects::projects.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('projects::projects.intro') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <label for="q" class="sr-only">{{ __('projects::projects.search') }}</label>
            <input id="q" type="search" wire:model.live.debounce.300ms="q" placeholder="{{ __('projects::projects.search') }}" class="rounded-md border-gray-300 text-sm">
            <label for="state" class="sr-only">{{ __('projects::projects.fields.status') }}</label>
            <select id="state" wire:model.live="state" class="rounded-md border-gray-300 text-sm">
                <option value="open">{{ __('projects::projects.filter.open') }}</option>
                <option value="closed">{{ __('projects::projects.filter.closed') }}</option>
                <option value="">{{ __('projects::projects.filter.all') }}</option>
            </select>
            @if (auth()->user()?->can('projects.project.view') || auth()->user()?->can('projects.project.manage'))
                <a href="{{ route('projects.portfolio') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('projects::governance.portfolio') }}</a>
            @endif
            <a href="{{ route('projects.tasks.mine') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('projects::projects.my_tasks') }}</a>
            @can('create', \Modules\Projects\Domain\Models\Project::class)
                <a href="{{ route('projects.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('projects::projects.new') }}</a>
            @endcan
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs text-gray-500">
                <tr>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('projects::projects.fields.name') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('projects::projects.fields.phase') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('projects::projects.fields.manager') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('projects::projects.fields.planned_end') }}</th>
                    <th scope="col" class="py-2 font-medium">{{ __('projects::projects.fields.tasks') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($projects as $project)
                    <tr wire:key="project-{{ $project->id }}">
                        <td class="py-2 pr-4">
                            <a href="{{ route('projects.show', $project) }}" class="font-medium text-blue-700 underline">{{ $project->code }} · {{ $project->name }}</a>
                            <span class="block text-xs text-gray-500">{{ $project->kind->label() }} · {{ $project->status->label() }}</span>
                        </td>
                        <td class="py-2 pr-4">{{ $project->currentPhase()?->key->label() ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $project->manager?->name ?? '—' }}</td>
                        <td @class(['py-2 pr-4', 'font-medium text-red-700' => $project->isOpen() && $project->planned_end?->isPast()])>{{ $project->planned_end?->toDateString() ?? '—' }}</td>
                        <td class="py-2">{{ __('projects::projects.progress', ['done' => $project->done_count, 'total' => $project->tasks_count]) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-2 text-gray-600">{{ __('projects::projects.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $projects->links() }}</div>
    </div>
</div>
