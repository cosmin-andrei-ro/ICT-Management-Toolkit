<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Bia\Application\Actions\StartAssessment;
use Modules\Bia\Domain\Models\BusinessProcess;

/**
 * One process: who owns it, its recovery objectives in force, and every
 * version of its assessment.
 *
 * @property-read BusinessProcess $process Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowProcess extends Component
{
    #[Locked]
    public string $processId = '';

    public function mount(BusinessProcess $process): void
    {
        $this->authorize('view', $process);
        $this->processId = $process->id;
    }

    #[Computed]
    public function process(): BusinessProcess
    {
        return BusinessProcess::query()->with(['department', 'owner', 'deputy'])->findOrFail($this->processId);
    }

    public function startAssessment(StartAssessment $start): void
    {
        $this->authorize('assess', $this->process);
        $this->redirectRoute('bia.assessments.show', $start->handle($this->process));
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->process);
        $this->process->delete();
        $this->redirectRoute('bia.processes.index');
    }

    public function render(): View
    {
        $process = $this->process;

        return view('bia::livewire.processes.show', [
            'process' => $process,
            'assessments' => $process->assessments()->with(['submitter', 'decider'])->get(),
        ])->title($process->name);
    }
}
