<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Domain\Models\User;

/**
 * The processes the signed-in person owns or deputizes for, with what is
 * expected of them: an assessment to fill in, to fix, or a review due.
 */
#[Layout('core::components.layouts.app')]
final class MyProcesses extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        $processes = BusinessProcess::query()->with('department')->ownedBy($user)->where('status', 'active')->orderBy('name')->get();
        $open = Assessment::query()->whereIn('process_id', $processes->modelKeys())->whereIn('status', ['draft', 'submitted', 'returned'])->get()->keyBy('process_id');

        return view('bia::livewire.processes.mine', [
            'processes' => $processes,
            'open' => $open,
            'today' => now()->toDateString(),
        ])->title(__('bia::processes.mine.title'));
    }
}
