<div class="space-y-6">
    <header class="space-y-1">
        <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 underline">{{ __('projects::projects.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __('projects::projects.my_tasks') }}</h1>
        <p class="text-sm text-gray-600">{{ __('projects::projects.my_tasks_intro') }}</p>
    </header>
    @php($user = auth()->user())
    @if ($decisions->isNotEmpty())
        <section aria-labelledby="decisions-heading" class="space-y-2 rounded-lg bg-amber-50 p-4 ring-1 ring-amber-200">
            <h2 id="decisions-heading" class="text-sm font-semibold">{{ __('projects::governance.your_decisions') }}</h2>
            <ul class="text-sm">
                @foreach ($decisions as $gate)
                    <li wire:key="decision-{{ $gate->id }}"><a href="{{ route('projects.show', $gate->project) }}" class="text-blue-700 underline">{{ $gate->project->code }} · {{ $gate->project->name }}</a> — {{ $gate->closesProject() ? __('projects::governance.closing') : __('projects::governance.to_phase', ['phase' => $gate->toPhase?->key->label()]) }} <span class="text-xs text-gray-600">({{ $gate->requester?->name }})</span></li>
                @endforeach
            </ul>
        </section>
    @endif
    @forelse ($groups as $projectId => $tasks)
        @php($project = $tasks->first()->project)
        <section wire:key="group-{{ $projectId }}" aria-labelledby="group-{{ $projectId }}" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="group-{{ $projectId }}" class="text-sm font-semibold"><a href="{{ route('projects.show', $project) }}" class="text-blue-700 underline">{{ $project->code }} · {{ $project->name }}</a></h2>
            <div class="grid gap-2 md:grid-cols-2">
                @foreach ($tasks as $task)
                    <div wire:key="task-{{ $task->id }}">
                        <x-projects::task-card :task="$task" :can-manage="false" :user="$user" />
                        <p class="mt-1 px-1 text-xs text-gray-500">{{ $task->status->label() }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <p class="rounded-lg bg-white p-4 text-sm text-gray-600 ring-1 ring-gray-200">{{ __('projects::projects.no_my_tasks') }}</p>
    @endforelse
</div>
