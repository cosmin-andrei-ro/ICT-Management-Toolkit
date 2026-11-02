<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Http\Livewire;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Documentation\Application\Actions\SaveSpace;
use Modules\Documentation\Domain\Enums\SpaceVisibility;
use Modules\Documentation\Domain\Models\Space;

/**
 * Creates or changes a space: who reads it, who writes in it, who owns it.
 */
#[Layout('core::components.layouts.app')]
final class SpaceForm extends Component
{
    #[Locked]
    public ?string $spaceId = null;

    public string $name = '';

    public string $description = '';

    public string $visibility = 'staff';

    public bool $requiresApproval = false;

    public int $reviewMonths = 12;

    /** @var list<string> */
    public array $ownerIds = [];

    /** @var list<string> */
    public array $readTeamIds = [];

    /** @var list<string> */
    public array $editTeamIds = [];

    public function mount(?Space $space = null): void
    {
        if ($space === null || ! $space->exists) {
            $this->authorize('create', Space::class);
            $this->ownerIds = [$this->actor()->id];

            return;
        }

        $this->authorize('update', $space);
        $this->spaceId = $space->id;
        $this->name = $space->name;
        $this->description = $space->description ?? '';
        $this->visibility = $space->visibility->value;
        $this->requiresApproval = $space->requires_approval;
        $this->reviewMonths = $space->review_months;
        $this->ownerIds = array_values(array_filter($space->owners()->pluck('users.id')->all(), is_string(...)));

        foreach ($space->teamAccess() as $teamId => $access) {
            if ($access === 'edit') {
                $this->editTeamIds[] = $teamId;
            } else {
                $this->readTeamIds[] = $teamId;
            }
        }
    }

    public function save(SaveSpace $save, OrganizationContext $context): void
    {
        $space = $this->spaceId !== null ? Space::query()->findOrFail($this->spaceId) : null;
        $this->authorize($space !== null ? 'update' : 'create', $space ?? Space::class);
        $people = $this->people($context)->pluck('id')->all();
        $teams = $this->teams()->pluck('id')->all();

        $this->validate([
            'name' => ['required', 'string', 'max:150', $this->uniqueName(...)],
            'description' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['required', Rule::enum(SpaceVisibility::class)],
            'requiresApproval' => ['boolean'],
            'reviewMonths' => ['required', 'integer', 'between:1,60'],
            'ownerIds' => ['required', 'array', 'min:1', 'max:20'],
            'ownerIds.*' => ['uuid', Rule::in($people)],
            'readTeamIds' => ['array', 'max:50', Rule::requiredIf($this->visibility === SpaceVisibility::Teams->value && $this->editTeamIds === [])],
            'readTeamIds.*' => ['uuid', Rule::in($teams)],
            'editTeamIds' => ['array', 'max:50'],
            'editTeamIds.*' => ['uuid', Rule::in($teams)],
        ]);

        $saved = $save->handle($space, [
            'name' => $this->name,
            'description' => $this->description,
            'visibility' => SpaceVisibility::from($this->visibility),
            'requires_approval' => $this->requiresApproval,
            'review_months' => $this->reviewMonths,
        ], $this->ownerIds, $this->readTeamIds, $this->editTeamIds, $this->actor());

        $this->redirectRoute('documentation.spaces.show', $saved);
    }

    public function render(OrganizationContext $context): View
    {
        return view('documentation::livewire.space-form', [
            'people' => $this->people($context),
            'teams' => $this->teams(),
            'visibilities' => SpaceVisibility::cases(),
        ])->title(__($this->spaceId !== null ? 'documentation::docs.spaces.edit' : 'documentation::docs.spaces.new'));
    }

    private function uniqueName(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = is_string($value) && Space::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])
            ->when($this->spaceId !== null, fn ($query) => $query->whereKeyNot($this->spaceId))
            ->exists();

        if ($taken) {
            $fail(__('documentation::docs.spaces.name_taken'));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function people(OrganizationContext $context): Collection
    {
        return User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Team>
     */
    private function teams(): Collection
    {
        return Team::query()->orderBy('name')->get(['id', 'name']);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
