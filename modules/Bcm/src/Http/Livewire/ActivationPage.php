<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Livewire;

use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Bcm\Application\Activations\RecordActivation;
use Modules\Bcm\Domain\Models\Activation;
use Modules\Bcm\Http\Livewire\Concerns\LocalTimes;
use Modules\Core\Domain\Models\User;

/**
 * One activation: followed while the crisis lasts, closed once the service is
 * back, then kept as a record.
 *
 * @property-read Activation $activation Computed.
 */
#[Layout('core::components.layouts.app')]
final class ActivationPage extends Component
{
    use LocalTimes;

    #[Locked]
    public string $activationId = '';

    public string $restoredAt = '';

    public string $summary = '';

    public string $lessons = '';

    public function mount(Activation $activation): void
    {
        $this->authorize('view', $activation);
        $this->activationId = $activation->id;
        $this->restoredAt = $this->formatLocal($activation->restored_at);
        $this->summary = $activation->summary ?? '';
        $this->lessons = $activation->lessons ?? '';
    }

    #[Computed]
    public function activation(): Activation
    {
        return Activation::query()->with(['plan', 'version', 'activator', 'closer'])->findOrFail($this->activationId);
    }

    public function save(RecordActivation $record): void
    {
        $this->store($record, false);
    }

    public function close(RecordActivation $record): void
    {
        $this->store($record, true);
    }

    public function render(): View
    {
        $activation = $this->activation;

        return view('bcm::livewire.activations.show', [
            'activation' => $activation,
            'plan' => $activation->plan,
            'editable' => auth()->user()?->can('update', $activation) === true,
            'timezone' => self::displayTimezone(),
        ])->title(__('bcm::activations.ui.title', ['plan' => $activation->plan->name]));
    }

    private function store(RecordActivation $record, bool $close): void
    {
        $this->authorize('update', $this->activation);
        $this->validate([
            'restoredAt' => [$close ? 'required' : 'nullable', 'date_format:Y-m-d\TH:i'],
            'summary' => ['nullable', 'string', 'max:10000'],
            'lessons' => ['nullable', 'string', 'max:10000'],
        ]);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        try {
            $record->handle($this->activation, $this->parseLocal($this->restoredAt), $this->summary, $this->lessons, $close, $actor);
        } catch (InvalidArgumentException) {
            $this->addError('restoredAt', __('bcm::activations.errors.restored'));
        } finally {
            unset($this->activation);
        }
    }
}
