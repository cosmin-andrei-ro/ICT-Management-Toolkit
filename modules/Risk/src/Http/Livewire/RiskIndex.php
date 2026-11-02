<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Application\Exports\RiskRegisterExport;
use Modules\Risk\Domain\Enums\RiskCategory;
use Modules\Risk\Domain\Enums\RiskLevel;
use Modules\Risk\Domain\Enums\SuggestionStatus;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskSettings;
use Modules\Risk\Domain\Models\Suggestion;

/**
 * The register with its heat map: open risks by likelihood and impact.
 */
#[Layout('core::components.layouts.app')]
final class RiskIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $level = '';

    #[Url]
    public bool $closed = false;

    /** inherent: before treatment; current: the residual risk where assessed, else the inherent one. */
    #[Url]
    public string $map = 'current';

    /** A cell of the heat map: "likelihood-impact". */
    #[Url]
    public string $cell = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Risk::class);
    }

    public function updated(string $property): void
    {
        if ($property === 'map') {
            $this->cell = '';
        }

        if (in_array($property, ['search', 'category', 'level', 'closed', 'cell', 'map'], true)) {
            $this->resetPage();
        }
    }

    public function export(string $format, RequestExport $request): void
    {
        $this->authorize('viewAny', Risk::class);
        $user = auth()->user();
        $format = ExportFormat::tryFrom($format);

        if (! $user instanceof User || $format === null) {
            return;
        }

        // RequestExport checks the permission and records the export in the audit trail.
        $request->handle($user, RiskRegisterExport::TYPE, $format, []);
    }

    public function selectCell(int $likelihood, int $impact): void
    {
        $cell = $likelihood.'-'.$impact;
        $this->cell = $this->cell === $cell ? '' : $cell;
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Risk::class);
        $settings = RiskSettings::current();
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim(mb_substr($this->search, 0, 200))).'%';

        $filtered = Risk::query()
            ->when(! $this->closed, fn (Builder $query) => $query->where('status', 'open'))
            ->when(RiskCategory::tryFrom($this->category) !== null, fn (Builder $query) => $query->where('category', $this->category))
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereLike('title', $like)->orWhereLike('description', $like)));

        // Column expressions for the map shown; fixed strings, never user input.
        [$likelihoodColumn, $impactColumn] = $this->map === 'inherent'
            ? ['inherent_likelihood', 'inherent_impact']
            : ['coalesce(residual_likelihood, inherent_likelihood)', 'coalesce(residual_impact, inherent_impact)'];
        $heat = [];

        foreach ((clone $filtered)->whereNotNull('inherent_likelihood')->selectRaw("{$likelihoodColumn} as l, {$impactColumn} as i, count(*) as total")->groupByRaw("{$likelihoodColumn}, {$impactColumn}")->toBase()->get() as $row) {
            if (is_numeric($row->l) && is_numeric($row->i) && is_numeric($row->total)) {
                $heat[(int) $row->l][(int) $row->i] = (int) $row->total;
            }
        }

        [$likelihood, $impact] = array_map(intval(...), explode('-', $this->cell.'-'));
        $level = RiskLevel::tryFrom($this->level);
        $scoreRange = $level !== null ? self::range($settings, $level) : null;
        $perPage = config('risk.risks.per_page');

        $risks = (clone $filtered)
            ->with('owner')
            ->when($likelihood > 0 && $impact > 0, fn (Builder $query) => $query->whereRaw("{$likelihoodColumn} = ? AND {$impactColumn} = ?", [$likelihood, $impact]))
            ->when($scoreRange !== null, fn (Builder $query) => $query->whereRaw("{$likelihoodColumn} * {$impactColumn} BETWEEN ? AND ?", $scoreRange))
            ->orderByRaw("{$likelihoodColumn} * {$impactColumn} DESC NULLS LAST")
            ->orderBy('reference')
            ->paginate(is_int($perPage) ? $perPage : 25);

        return view('risk::livewire.risks.index', [
            'risks' => $risks,
            'heat' => $heat,
            'settings' => $settings,
            'categories' => RiskCategory::cases(),
            'levels' => RiskLevel::cases(),
            'exports' => $exports = Export::query()->where('user_id', auth()->id())->where('type', RiskRegisterExport::TYPE)->latest()->limit(3)->get(),
            'preparing' => $exports->contains(fn (Export $export): bool => $export->status === ExportStatus::Pending),
            'timezone' => config()->string('app.display_timezone'),
            'openSuggestions' => auth()->user()?->can('create', Risk::class) === true ? Suggestion::query()->where('status', SuggestionStatus::Open)->count() : null,
        ])->title(__('risk::risks.title'));
    }

    /**
     * @return array{int, int}
     */
    private static function range(RiskSettings $settings, RiskLevel $level): array
    {
        return match ($level) {
            RiskLevel::Low => [1, $settings->medium_from - 1],
            RiskLevel::Medium => [$settings->medium_from, $settings->high_from - 1],
            RiskLevel::High => [$settings->high_from, $settings->critical_from - 1],
            RiskLevel::Critical => [$settings->critical_from, 25],
        };
    }
}
