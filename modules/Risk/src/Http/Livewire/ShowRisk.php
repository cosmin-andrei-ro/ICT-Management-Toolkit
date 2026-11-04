<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Links\ProblemTickets;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Application\Actions\AcceptRisk;
use Modules\Risk\Application\Actions\AssessRisk;
use Modules\Risk\Application\Actions\ManageRiskActions;
use Modules\Risk\Application\Actions\ManageRiskAssets;
use Modules\Risk\Application\Actions\SaveTreatment;
use Modules\Risk\Application\Catalog\ProvisionCatalog;
use Modules\Risk\Application\RiskAssets;
use Modules\Risk\Domain\Enums\AcceptanceStatus;
use Modules\Risk\Domain\Enums\ActionStatus;
use Modules\Risk\Domain\Enums\AssetType;
use Modules\Risk\Domain\Enums\CatalogKind;
use Modules\Risk\Domain\Enums\Treatment;
use Modules\Risk\Domain\Models\Acceptance;
use Modules\Risk\Domain\Models\CatalogEntry;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskAction;
use Modules\Risk\Domain\Models\RiskSettings;

/**
 * One risk: what it concerns, its threats and vulnerabilities, the controls
 * in place and its inherent score, assessed by its owner.
 *
 * @property-read Risk $risk Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowRisk extends Component
{
    #[Locked]
    public string $riskId = '';

    public string $likelihood = '';

    public string $impact = '';

    /** @var list<string> */
    public array $entryIds = [];

    public string $controls = '';

    public string $treatment = '';

    public string $treatmentPlan = '';

    public string $residualLikelihood = '';

    public string $residualImpact = '';

    public string $actionTitle = '';

    public string $actionOwnerId = '';

    public string $actionDueOn = '';

    public string $justification = '';

    public string $decisionComment = '';

    public string $assetType = 'application';

    public string $assetSearch = '';

    public function mount(Risk $risk, ProvisionCatalog $catalog): void
    {
        $this->authorize('view', $risk);
        $catalog->handle();
        $this->riskId = $risk->id;
        $this->likelihood = (string) ($risk->inherent_likelihood ?? '');
        $this->impact = (string) ($risk->inherent_impact ?? '');
        $this->entryIds = array_values(array_filter($risk->catalogEntries()->pluck('risk_catalog_entries.id')->all(), is_string(...)));
        $this->controls = $risk->existing_controls ?? '';
        $this->treatment = $risk->treatment->value ?? '';
        $this->treatmentPlan = $risk->treatment_plan ?? '';
        $this->residualLikelihood = (string) ($risk->residual_likelihood ?? '');
        $this->residualImpact = (string) ($risk->residual_impact ?? '');
    }

    #[Computed]
    public function risk(): Risk
    {
        return Risk::query()->with('owner')->findOrFail($this->riskId);
    }

    public function assess(AssessRisk $assess): void
    {
        $this->authorize('assess', $this->risk);
        $this->validate([
            'likelihood' => ['nullable', 'integer', 'between:1,5', 'required_with:impact'],
            'impact' => ['nullable', 'integer', 'between:1,5', 'required_with:likelihood'],
            'entryIds' => ['array', 'max:100'],
            'entryIds.*' => ['uuid'],
            'controls' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $assess->handle($this->risk, $this->likelihood !== '' ? (int) $this->likelihood : null, $this->impact !== '' ? (int) $this->impact : null, array_values(array_filter($this->entryIds, is_string(...))), $this->controls, $this->actor());
        } catch (InvalidArgumentException) {
            $this->addError('entryIds', __('risk::risks.errors.invalid'));

            return;
        } finally {
            unset($this->risk);
        }

        $this->dispatch('saved');
    }

    public function saveTreatment(SaveTreatment $save): void
    {
        $this->authorize('assess', $this->risk);
        $this->validate([
            'treatment' => ['nullable', Rule::enum(Treatment::class)],
            'treatmentPlan' => ['nullable', 'string', 'max:10000'],
            'residualLikelihood' => ['nullable', 'integer', 'between:1,5', 'required_with:residualImpact'],
            'residualImpact' => ['nullable', 'integer', 'between:1,5', 'required_with:residualLikelihood'],
        ]);

        try {
            $save->handle($this->risk, Treatment::tryFrom($this->treatment), $this->treatmentPlan, $this->residualLikelihood !== '' ? (int) $this->residualLikelihood : null, $this->residualImpact !== '' ? (int) $this->residualImpact : null, $this->actor());
        } catch (InvalidArgumentException) {
            $this->addError('residualLikelihood', __('risk::risks.errors.invalid'));
        } finally {
            unset($this->risk);
        }
    }

    public function addAction(ManageRiskActions $actions): void
    {
        $this->authorize('assess', $this->risk);
        $this->validate([
            'actionTitle' => ['required', 'string', 'max:255'],
            'actionOwnerId' => ['nullable', 'uuid'],
            'actionDueOn' => ['nullable', 'date_format:Y-m-d'],
        ]);

        try {
            $actions->add($this->risk, $this->actionTitle, $this->actionOwnerId !== '' ? $this->actionOwnerId : null, $this->actionDueOn !== '' ? CarbonImmutable::createFromFormat('Y-m-d', $this->actionDueOn) ?: null : null, $this->actor());
            $this->reset('actionTitle', 'actionOwnerId', 'actionDueOn');
        } catch (InvalidArgumentException) {
            $this->addError('actionTitle', __('risk::risks.errors.invalid'));
        }
    }

    public function changeActionStatus(string $actionId, string $status, ManageRiskActions $actions): void
    {
        $this->authorize('assess', $this->risk);
        $action = RiskAction::query()->where('risk_id', $this->riskId)->findOrFail($actionId);
        $target = ActionStatus::tryFrom($status);

        if ($target !== null) {
            $actions->changeStatus($action, $target, $this->actor());
        }
    }

    public function raiseActionTicket(string $actionId, ManageRiskActions $actions): void
    {
        $this->authorize('assess', $this->risk);
        $action = RiskAction::query()->where('risk_id', $this->riskId)->findOrFail($actionId);

        try {
            $actions->raiseTicket($action, $this->actor());
        } catch (InvalidArgumentException) {
            $this->addError('actions', __('risk::risks.errors.invalid'));
        }
    }

    public function requestAcceptance(AcceptRisk $accept): void
    {
        $this->authorize('assess', $this->risk);
        $this->validate(['justification' => ['required', 'string', 'max:5000']]);

        try {
            $accept->request($this->risk, $this->justification, $this->actor());
            $this->reset('justification');
        } catch (InvalidArgumentException) {
            $this->addError('justification', __('risk::treatment.errors.cannot_request'));
        } finally {
            unset($this->risk);
        }
    }

    public function approveAcceptance(AcceptRisk $accept): void
    {
        $this->decideAcceptance($accept, true);
    }

    public function rejectAcceptance(AcceptRisk $accept): void
    {
        $this->decideAcceptance($accept, false);
    }

    public function addAsset(string $type, string $id, ManageRiskAssets $assets): void
    {
        $this->authorize('assess', $this->risk);
        $kind = AssetType::tryFrom($type);

        try {
            $kind !== null ? $assets->add($this->risk, $kind, $id, $this->actor()) : throw new InvalidArgumentException;
            $this->reset('assetSearch');
        } catch (InvalidArgumentException) {
            $this->addError('assetSearch', __('risk::risks.errors.invalid'));
        }
    }

    public function removeAsset(string $type, string $id, ManageRiskAssets $assets): void
    {
        $this->authorize('assess', $this->risk);
        $kind = AssetType::tryFrom($type);

        if ($kind !== null) {
            $assets->remove($this->risk, $kind, $id, $this->actor());
        }
    }

    private function decideAcceptance(AcceptRisk $accept, bool $approve): void
    {
        $this->authorize('view', $this->risk);
        $this->validate(['decisionComment' => [$approve ? 'nullable' : 'required', 'string', 'max:2000']]);
        $pending = Acceptance::query()->where('risk_id', $this->riskId)->where('status', AcceptanceStatus::Pending)->firstOrFail();

        try {
            $accept->decide($pending, $approve, $this->decisionComment, $this->actor());
            $this->reset('decisionComment');
        } catch (AuthorizationException) {
            $this->addError('decisionComment', __('risk::treatment.errors.cannot_decide'));
        } catch (InvalidArgumentException) {
            $this->addError('decisionComment', __('risk::risks.errors.invalid'));
        } finally {
            unset($this->risk);
        }
    }

    public function render(RiskAssets $assets, AcceptRisk $accept, OrganizationContext $context): View
    {
        $risk = $this->risk;
        $settings = RiskSettings::current();
        $type = AssetType::tryFrom($this->assetType) ?? AssetType::Application;
        $assessable = auth()->user()?->can('assess', $risk) === true;
        $entries = CatalogEntry::query()->orderBy('name')->get();
        $score = $risk->inherentScore();
        $residual = $risk->residualScore();
        $pending = $risk->acceptances()->with('requester')->where('status', AcceptanceStatus::Pending)->first();
        $user = $this->actor();

        return view('risk::livewire.risks.show', [
            'risk' => $risk,
            'settings' => $settings,
            'assessable' => $assessable,
            'score' => $score,
            'level' => $score !== null ? $settings->level($score) : null,
            'residual' => $residual,
            'residualLevel' => $residual !== null ? $settings->level($residual) : null,
            'needsApproval' => $residual !== null && $residual >= $settings->approval_from,
            'treatments' => Treatment::cases(),
            'actions' => $risk->actions()->with('owner')->get(),
            'actionStatuses' => ActionStatus::cases(),
            'ticketsAvailable' => app(ProblemTickets::class)->isAvailable(),
            'pending' => $pending,
            'canDecide' => $pending !== null && $accept->mayDecide($user, $pending, $risk),
            'acceptances' => $risk->acceptances()->with(['requester', 'decider'])->limit(10)->get(),
            'history' => $risk->scoreHistory()->with('recorder')->limit(20)->get(),
            'people' => $assessable ? User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']) : collect(),
            'linked' => $assets->resolve($risk->assets()),
            'assetTypes' => array_values(array_filter(AssetType::cases(), fn (AssetType $candidate): bool => $assets->isAvailable($candidate))),
            'candidates' => $assessable ? $assets->search($type, $this->assetSearch) : [],
            // Inactive entries stay visible on risks that use them.
            'threats' => $entries->filter(fn (CatalogEntry $entry): bool => $entry->kind === CatalogKind::Threat && ($entry->is_active || in_array($entry->id, $this->entryIds, true)))->values(),
            'vulnerabilities' => $entries->filter(fn (CatalogEntry $entry): bool => $entry->kind === CatalogKind::Vulnerability && ($entry->is_active || in_array($entry->id, $this->entryIds, true)))->values(),
        ])->title($risk->code().' · '.$risk->title);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
