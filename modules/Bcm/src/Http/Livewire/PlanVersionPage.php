<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Livewire;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Bcm\Application\Actions\DecidePlanVersion;
use Modules\Bcm\Application\Actions\ManagePlanContent;
use Modules\Bcm\Application\Actions\SavePlanVersion;
use Modules\Bcm\Application\Actions\SubmitPlanVersion;
use Modules\Bcm\Application\PlanTiming;
use Modules\Bcm\Domain\Enums\Phase;
use Modules\Bcm\Domain\Enums\Scenario;
use Modules\Bcm\Domain\Exceptions\VersionLocked;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Bcm\Domain\Models\Step;
use Modules\Bcm\Domain\Models\TeamMember;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Time\Duration;

/**
 * One version of a plan: written by its owner (or a manager) while open, read
 * by everyone who may see the plan, decided by an approver once submitted.
 *
 * @property-read PlanVersion $version Computed.
 */
#[Layout('core::components.layouts.app')]
final class PlanVersionPage extends Component
{
    #[Locked]
    public string $versionId = '';

    public string $objective = '';

    /** @var list<string> */
    public array $scenarios = [];

    public string $activationCriteria = '';

    public string $activationAuthority = '';

    public string $communication = '';

    public string $strategy = '';

    public string $assumptions = '';

    public string $processSearch = '';

    public string $itemSearch = '';

    public string $memberRole = '';

    public string $memberUserId = '';

    public string $memberContactName = '';

    public string $memberContactPhone = '';

    public string $memberContactEmail = '';

    public bool $memberDeputy = false;

    public string $memberResponsibilities = '';

    /** The step being edited; "new" while adding one; null when the form is closed. */
    #[Locked]
    public ?string $editingStepId = null;

    public string $stepPhase = 'recovery';

    public string $stepAction = '';

    public string $stepResponsible = '';

    public string $stepResponsibleUserId = '';

    public string $stepDurationValue = '';

    public string $stepDurationUnit = 'minutes';

    public string $stepItemId = '';

    public string $stepNotes = '';

    public string $comment = '';

    public function mount(PlanVersion $version): void
    {
        $this->authorize('view', $version);
        $this->versionId = $version->id;
        $this->objective = $version->objective ?? '';
        $this->scenarios = $version->scenarios;
        $this->activationCriteria = $version->activation_criteria ?? '';
        $this->activationAuthority = $version->activation_authority ?? '';
        $this->communication = $version->communication ?? '';
        $this->strategy = $version->strategy ?? '';
        $this->assumptions = $version->assumptions ?? '';
    }

    #[Computed]
    public function version(): PlanVersion
    {
        return PlanVersion::query()->with(['plan.owner', 'plan.deputy', 'submitter', 'decider'])->findOrFail($this->versionId);
    }

    public function save(SavePlanVersion $savePlan): void
    {
        $this->authorize('update', $this->version);
        $this->store($savePlan);
    }

    public function submit(SavePlanVersion $savePlan, SubmitPlanVersion $submitPlan): void
    {
        $this->authorize('update', $this->version);
        $this->store($savePlan);

        try {
            $submitPlan->handle($this->version, $this->actor());
        } catch (VersionLocked) {
            $this->addError('scope', __('bcm::plans.errors.locked'));
        } finally {
            unset($this->version);
        }
    }

    public function approve(DecidePlanVersion $decide): void
    {
        $this->decide($decide, true);
    }

    public function sendBack(DecidePlanVersion $decide): void
    {
        $this->decide($decide, false);
    }

    public function addProcess(string $processId, ManagePlanContent $content): void
    {
        $this->authorize('update', $this->version);

        if ($this->change(fn () => $content->addProcess($this->version, $processId, $this->actor()), 'processSearch')) {
            $this->reset('processSearch');
        }
    }

    public function removeProcess(string $processId, ManagePlanContent $content): void
    {
        $this->authorize('update', $this->version);
        $this->change(fn () => $content->removeProcess($this->version, $processId, $this->actor()), 'processSearch');
    }

    public function linkItem(string $itemId, ManagePlanContent $content): void
    {
        $this->authorize('update', $this->version);

        if ($this->change(fn () => $content->linkItem($this->version, $itemId, $this->actor()), 'itemSearch')) {
            $this->reset('itemSearch');
        }
    }

    public function unlinkItem(string $itemId, ManagePlanContent $content): void
    {
        $this->authorize('update', $this->version);
        $this->change(fn () => $content->unlinkItem($this->version, $itemId, $this->actor()), 'itemSearch');
    }

    public function addMember(ManagePlanContent $content): void
    {
        $this->authorize('update', $this->version);
        $this->validate([
            'memberRole' => ['required', 'string', 'max:255'],
            'memberContactName' => ['nullable', 'string', 'max:255'],
            'memberContactPhone' => ['nullable', 'string', 'max:64'],
            'memberContactEmail' => ['nullable', 'string', 'max:255', 'email'],
            'memberResponsibilities' => ['nullable', 'string', 'max:2000'],
        ]);

        $added = $this->change(fn () => $content->addMember($this->version, [
            'role' => $this->memberRole,
            'user_id' => $this->memberUserId,
            'contact_name' => $this->memberContactName,
            'contact_phone' => $this->memberContactPhone,
            'contact_email' => $this->memberContactEmail,
            'is_deputy' => $this->memberDeputy,
            'responsibilities' => $this->memberResponsibilities,
        ], $this->actor()), 'team');

        if ($added) {
            $this->reset('memberRole', 'memberUserId', 'memberContactName', 'memberContactPhone', 'memberContactEmail', 'memberDeputy', 'memberResponsibilities');
        }
    }

    public function removeMember(string $memberId, ManagePlanContent $content): void
    {
        $this->authorize('update', $this->version);
        $member = TeamMember::query()->where('version_id', $this->versionId)->findOrFail($memberId);
        $this->change(fn () => $content->removeMember($member, $this->actor()), 'team');
    }

    public function editStep(?string $stepId = null): void
    {
        $this->authorize('update', $this->version);
        $this->resetErrorBag();
        $step = $stepId !== null ? Step::query()->where('version_id', $this->versionId)->findOrFail($stepId) : null;

        $this->editingStepId = $step->id ?? 'new';
        $this->stepPhase = $step !== null ? $step->phase->value : 'recovery';
        $this->stepAction = $step->action ?? '';
        $this->stepResponsible = $step->responsible ?? '';
        $this->stepResponsibleUserId = $step->responsible_user_id ?? '';
        ['value' => $this->stepDurationValue, 'unit' => $this->stepDurationUnit] = Duration::split($step?->estimated_minutes);
        $this->stepItemId = $step->configuration_item_id ?? '';
        $this->stepNotes = $step->notes ?? '';
    }

    public function cancelStep(): void
    {
        $this->reset('editingStepId', 'stepPhase', 'stepAction', 'stepResponsible', 'stepResponsibleUserId', 'stepDurationValue', 'stepDurationUnit', 'stepItemId', 'stepNotes');
    }

    public function saveStep(ManagePlanContent $content): void
    {
        $this->authorize('update', $this->version);

        if ($this->editingStepId === null) {
            return;
        }

        $this->validate([
            'stepPhase' => ['required', Rule::enum(Phase::class)],
            'stepAction' => ['required', 'string', 'max:5000'],
            'stepResponsible' => ['nullable', 'string', 'max:255'],
            'stepDurationValue' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'stepDurationUnit' => ['required', Rule::in(array_keys(Duration::UNITS))],
            'stepNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $existing = $this->editingStepId === 'new' ? null : Step::query()->where('version_id', $this->versionId)->findOrFail($this->editingStepId);

        $saved = $this->change(fn () => $content->saveStep($this->version, $existing, [
            'phase' => $this->stepPhase,
            'action' => $this->stepAction,
            'responsible' => $this->stepResponsible,
            'responsible_user_id' => $this->stepResponsibleUserId,
            'estimated_minutes' => Duration::toMinutes($this->stepDurationValue, $this->stepDurationUnit),
            'configuration_item_id' => $this->stepItemId,
            'notes' => $this->stepNotes,
        ], $this->actor()), 'stepAction');

        if ($saved) {
            $this->cancelStep();
        }
    }

    public function removeStep(string $stepId, ManagePlanContent $content): void
    {
        $this->authorize('update', $this->version);
        $step = Step::query()->where('version_id', $this->versionId)->findOrFail($stepId);
        $this->change(fn () => $content->removeStep($step, $this->actor()), 'steps');
    }

    public function moveStepUp(string $stepId, ManagePlanContent $content): void
    {
        $this->move($stepId, -1, $content);
    }

    public function moveStepDown(string $stepId, ManagePlanContent $content): void
    {
        $this->move($stepId, 1, $content);
    }

    public function render(BusinessProcesses $processes, ConfigurationItems $items, PlanTiming $timing, OrganizationContext $context): View
    {
        $version = $this->version;
        $editable = auth()->user()?->can('update', $version) === true;
        $processSearch = trim($this->processSearch);
        $itemSearch = trim($this->itemSearch);
        $linkedItems = $items->linkedTo($version);

        return view('bcm::livewire.versions.show', [
            'version' => $version,
            'plan' => $version->plan,
            'editable' => $editable,
            'canDecide' => auth()->user()?->can('decide', $version) === true,
            'allScenarios' => Scenario::cases(),
            'phases' => Phase::cases(),
            'units' => array_keys(Duration::UNITS),
            'processesAvailable' => $processes->isAvailable(),
            'cmdbAvailable' => $items->isAvailable(),
            'scopeProcesses' => array_values($processes->find($version->processIds())),
            'processCandidates' => $editable && mb_strlen($processSearch) >= 2 ? $processes->search($processSearch) : [],
            'linkedItems' => $linkedItems,
            'itemNames' => collect($linkedItems)->pluck('name', 'id')->all(),
            'itemCandidates' => $editable && mb_strlen($itemSearch) >= 2 ? $items->search($itemSearch) : [],
            'team' => $version->team()->with('user')->get(),
            'steps' => $version->steps()->with('responsibleUser')->get(),
            'estimate' => $version->estimatedRecoveryMinutes(),
            'strictest' => $timing->strictestRto($version),
            'people' => $editable ? User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']) : collect(),
        ])->title($version->plan->name.' · v'.$version->version);
    }

    private function store(SavePlanVersion $savePlan): void
    {
        $this->validate([
            'objective' => ['nullable', 'string', 'max:5000'],
            'scenarios' => ['array'],
            'scenarios.*' => [Rule::enum(Scenario::class)],
            'activationCriteria' => ['nullable', 'string', 'max:5000'],
            'activationAuthority' => ['nullable', 'string', 'max:5000'],
            'communication' => ['nullable', 'string', 'max:10000'],
            'strategy' => ['nullable', 'string', 'max:10000'],
            'assumptions' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $savePlan->handle($this->version, [
                'objective' => $this->objective,
                'scenarios' => array_values(array_filter($this->scenarios, is_string(...))),
                'activation_criteria' => $this->activationCriteria,
                'activation_authority' => $this->activationAuthority,
                'communication' => $this->communication,
                'strategy' => $this->strategy,
                'assumptions' => $this->assumptions,
            ]);
        } catch (VersionLocked) {
            throw ValidationException::withMessages(['scope' => __('bcm::plans.errors.locked')]);
        } finally {
            unset($this->version);
        }
    }

    private function decide(DecidePlanVersion $decide, bool $approve): void
    {
        $this->authorize('decide', $this->version);
        $this->validate(['comment' => ['nullable', 'string', 'max:5000']]);

        try {
            $decide->handle($this->version, $this->actor(), $approve, $this->comment);
        } catch (AuthorizationException) {
            $this->addError('comment', __('bcm::plans.errors.cannot_decide'));

            return;
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['comment' => $exception->errors()['comment'] ?? []]);
        } finally {
            unset($this->version);
        }

        $this->reset('comment');
    }

    private function move(string $stepId, int $direction, ManagePlanContent $content): void
    {
        $this->authorize('update', $this->version);
        $step = Step::query()->where('version_id', $this->versionId)->findOrFail($stepId);
        $this->change(fn () => $content->moveStep($step, $direction), 'steps');
    }

    private function change(Closure $change, string $errorKey): bool
    {
        try {
            $change();

            return true;
        } catch (VersionLocked) {
            $this->addError($errorKey, __('bcm::plans.errors.locked'));
        } catch (InvalidArgumentException) {
            $this->addError($errorKey, __('bcm::plans.errors.invalid'));
        } finally {
            unset($this->version);
        }

        return false;
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
