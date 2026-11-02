<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Livewire;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Bia\Application\Gaps\GapAnalysis;
use Modules\Bia\Application\Gaps\GapRow;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\User;

/**
 * Configuration items with the recovery the business requires of them and what
 * IT declared it can deliver. People who may change CMDB items can align an
 * item's criticality with the BIA.
 */
#[Layout('core::components.layouts.app')]
final class GapReport extends Component
{
    /** Only items whose capability falls short or is not declared. */
    #[Url]
    public bool $problems = true;

    public function mount(): void
    {
        $this->authorize('viewAny', BusinessProcess::class);
    }

    public function alignCriticality(string $itemId, GapAnalysis $analysis, ConfigurationItems $items): void
    {
        $this->authorize('viewAny', BusinessProcess::class);
        $actor = $this->actor();
        $row = collect($analysis->rows())->first(fn (GapRow $row): bool => $row->item->id === $itemId);

        if ($row === null) {
            return;
        }

        try {
            $items->changeCriticality($itemId, $row->suggestedCriticality, $actor);
        } catch (AuthorizationException) {
            abort(403);
        } catch (InvalidArgumentException) {
            $this->addError('criticality', __('bia::gaps.invalid'));
        }
    }

    public function render(GapAnalysis $analysis, ConfigurationItems $items): View
    {
        $this->authorize('viewAny', BusinessProcess::class);
        $rows = $analysis->rows();

        return view('bia::livewire.gaps', [
            'rows' => $this->problems ? array_values(array_filter($rows, fn (GapRow $row): bool => $row->status() !== GapRow::MET)) : $rows,
            'total' => count($rows),
            'counts' => array_count_values(array_map(fn (GapRow $row): string => $row->status(), $rows)),
            'cmdbAvailable' => $items->isAvailable(),
            'canAlign' => $items->canChangeCriticality($this->actor()),
        ])->title(__('bia::gaps.title'));
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
