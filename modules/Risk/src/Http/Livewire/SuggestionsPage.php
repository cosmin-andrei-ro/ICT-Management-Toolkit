<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Livewire;

use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Application\RiskAssets;
use Modules\Risk\Application\Suggestions\DecideSuggestion;
use Modules\Risk\Application\Suggestions\RefreshSuggestions;
use Modules\Risk\Domain\Enums\SuggestionStatus;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\Suggestion;

/**
 * Risks the other modules suggest (owner's decision: to review, never created
 * silently): turn one into a prefilled risk, or dismiss it with a reason.
 */
#[Layout('core::components.layouts.app')]
final class SuggestionsPage extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'open';

    #[Url]
    public string $source = '';

    /** @var array<string, string> Owner chosen per suggestion id. */
    public array $owners = [];

    /** @var array<string, string> Dismissal reason per suggestion id. */
    public array $reasons = [];

    public string $notice = '';

    public function mount(): void
    {
        $this->authorize('create', Risk::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'source'], true)) {
            $this->resetPage();
        }
    }

    public function refreshNow(RefreshSuggestions $refresh): void
    {
        $this->authorize('create', Risk::class);
        $result = $refresh->handle();
        $this->notice = (string) __('risk::suggestions.ui.refreshed', ['opened' => $result['opened'], 'resolved' => $result['resolved']]);
    }

    public function convert(string $suggestionId, DecideSuggestion $decide): void
    {
        $this->authorize('create', Risk::class);
        $suggestion = Suggestion::query()->findOrFail($suggestionId);
        $this->resetErrorBag('owners.'.$suggestionId);
        $ownerId = $this->owners[$suggestionId] ?? $suggestion->owner_id ?? '';

        try {
            $risk = $decide->convert($suggestion, $ownerId, $this->actor());
        } catch (InvalidArgumentException) {
            $this->addError('owners.'.$suggestionId, __('risk::suggestions.errors.owner'));

            return;
        }

        $this->redirectRoute('risk.risks.show', $risk);
    }

    public function dismiss(string $suggestionId, DecideSuggestion $decide): void
    {
        $this->authorize('create', Risk::class);
        $suggestion = Suggestion::query()->findOrFail($suggestionId);
        $this->resetErrorBag('reasons.'.$suggestionId);

        try {
            $decide->dismiss($suggestion, $this->reasons[$suggestionId] ?? '', $this->actor());
        } catch (InvalidArgumentException) {
            $this->addError('reasons.'.$suggestionId, __('risk::suggestions.errors.reason'));
        }
    }

    public function render(OrganizationContext $context, RiskAssets $assets): View
    {
        $this->authorize('create', Risk::class);
        $status = SuggestionStatus::tryFrom($this->status) ?? SuggestionStatus::Open;

        $suggestions = Suggestion::query()
            ->with(['risk', 'decider'])
            ->where('status', $status)
            ->when($this->source !== '', fn ($query) => $query->where('source', $this->source))
            ->orderBy('source_label')->orderBy('title')
            ->paginate(25);

        return view('risk::livewire.suggestions', [
            'suggestions' => $suggestions,
            'linked' => $suggestions->getCollection()->mapWithKeys(fn (Suggestion $suggestion): array => [$suggestion->id => $assets->resolve($suggestion->assets)])->all(),
            'statuses' => SuggestionStatus::cases(),
            'sources' => Suggestion::query()->distinct()->orderBy('source_label')->pluck('source_label', 'source')->all(),
            'counts' => Suggestion::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'people' => User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']),
        ])->title(__('risk::suggestions.title'));
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
