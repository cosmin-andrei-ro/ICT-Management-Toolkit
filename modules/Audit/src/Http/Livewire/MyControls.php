<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Audit\Domain\Enums\FindingStatus;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Finding;
use Modules\Core\Domain\Models\User;

/**
 * The controls, open findings and corrective actions of the signed-in
 * person, for owners outside the readers of compliance.
 */
#[Layout('core::components.layouts.app')]
final class MyControls extends Component
{
    public static function hasWork(User $user): bool
    {
        return Control::query()->ownedBy($user)->exists()
            || Finding::query()->where('status', '!=', FindingStatus::Closed->value)->where(fn ($query) => $query->where('owner_id', $user->id)->orWhereHas('actions', fn ($actions) => $actions->where('owner_id', $user->id)))->exists();
    }

    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return view('audit::livewire.controls.mine', [
            'controls' => Control::query()->ownedBy($user)->orderBy('number')->limit(200)->get(),
            'findings' => Finding::query()->with('actions')->where('status', '!=', FindingStatus::Closed->value)
                ->where(fn ($query) => $query->where('owner_id', $user->id)->orWhereHas('actions', fn ($actions) => $actions->where('owner_id', $user->id)))
                ->orderBy('due_on')->limit(200)->get(),
            'today' => CarbonImmutable::today(),
        ])->title(__('audit::compliance.mine.title'));
    }
}
