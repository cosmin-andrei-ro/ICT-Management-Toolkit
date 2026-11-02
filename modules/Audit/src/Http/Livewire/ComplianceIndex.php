<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Audit\Application\Actions\AddFramework;
use Modules\Audit\Application\Frameworks\Coverage;
use Modules\Audit\Application\Frameworks\FrameworkCatalog;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Framework;

/**
 * The frameworks the organization complies with and how far each is met.
 */
#[Layout('core::components.layouts.app')]
final class ComplianceIndex extends Component
{
    public bool $archived = false;

    public string $catalogKey = '';

    public string $customName = '';

    public string $customVersion = '';

    public string $customDescription = '';

    public bool $customSoa = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Framework::class);
    }

    public function addFromCatalog(AddFramework $add): void
    {
        $this->authorize('create', Framework::class);
        $this->validate(['catalogKey' => ['required', Rule::in(FrameworkCatalog::KEYS)]]);

        try {
            $framework = $add->fromCatalog($this->catalogKey);
        } catch (InvalidArgumentException) {
            $this->addError('catalogKey', __('audit::compliance.errors.already_added'));

            return;
        }

        $this->redirectRoute('audit.frameworks.show', $framework);
    }

    public function addCustom(AddFramework $add): void
    {
        $this->authorize('create', Framework::class);
        $this->validate([
            'customName' => ['required', 'string', 'max:255'],
            'customVersion' => ['nullable', 'string', 'max:64'],
            'customDescription' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $framework = $add->custom($this->customName, $this->customVersion, $this->customDescription, $this->customSoa);
        } catch (InvalidArgumentException) {
            $this->addError('customName', __('audit::compliance.errors.name_taken'));

            return;
        }

        $this->redirectRoute('audit.frameworks.show', $framework);
    }

    public function render(Coverage $coverage): View
    {
        $this->authorize('viewAny', Framework::class);
        $frameworks = Framework::query()->where('is_active', ! $this->archived)->orderBy('name')->get();
        $added = array_filter(Framework::query()->pluck('key')->all(), is_string(...));

        return view('audit::livewire.frameworks.index', [
            'frameworks' => $frameworks,
            'coverage' => $frameworks->mapWithKeys(fn (Framework $framework): array => [$framework->id => $coverage->of($framework)]),
            'catalog' => array_diff_key(FrameworkCatalog::available(), array_flip($added)),
            'controls' => Control::query()->count(),
        ])->title(__('audit::compliance.title'));
    }
}
