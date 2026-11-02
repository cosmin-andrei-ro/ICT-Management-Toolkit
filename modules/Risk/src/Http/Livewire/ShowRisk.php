<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Livewire;

use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Application\Actions\AssessRisk;
use Modules\Risk\Application\Actions\ManageRiskAssets;
use Modules\Risk\Application\Catalog\ProvisionCatalog;
use Modules\Risk\Application\RiskAssets;
use Modules\Risk\Domain\Enums\AssetType;
use Modules\Risk\Domain\Enums\CatalogKind;
use Modules\Risk\Domain\Models\CatalogEntry;
use Modules\Risk\Domain\Models\Risk;
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
            $assess->handle($this->risk, $this->likelihood !== '' ? (int) $this->likelihood : null, $this->impact !== '' ? (int) $this->impact : null, array_values(array_filter($this->entryIds, is_string(...))), $this->controls);
        } catch (InvalidArgumentException) {
            $this->addError('entryIds', __('risk::risks.errors.invalid'));

            return;
        } finally {
            unset($this->risk);
        }

        $this->dispatch('saved');
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

    public function render(RiskAssets $assets): View
    {
        $risk = $this->risk;
        $settings = RiskSettings::current();
        $type = AssetType::tryFrom($this->assetType) ?? AssetType::Application;
        $assessable = auth()->user()?->can('assess', $risk) === true;
        $entries = CatalogEntry::query()->orderBy('name')->get();
        $score = $risk->inherentScore();

        return view('risk::livewire.risks.show', [
            'risk' => $risk,
            'settings' => $settings,
            'assessable' => $assessable,
            'score' => $score,
            'level' => $score !== null ? $settings->level($score) : null,
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
