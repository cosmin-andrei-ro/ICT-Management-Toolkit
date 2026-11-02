<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Applications\Application\Actions\StartSheet;
use Modules\Applications\Application\Options\OptionCatalog;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Core\Domain\Links\KnowledgeArticles;
use Modules\Core\Domain\Links\Risks;
use Modules\Core\Domain\Models\User;

/**
 * One application: identity, the version in force, the change history and the
 * approvals, as in the template's first pages.
 *
 * @property-read Application $application Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowApplication extends Component
{
    #[Locked]
    public string $applicationId = '';

    public function mount(Application $application): void
    {
        $this->authorize('view', $application);
        $this->applicationId = $application->id;
    }

    #[Computed]
    public function application(): Application
    {
        return Application::query()->with(['department', 'businessOwner', 'technicalOwner', 'deputy'])->findOrFail($this->applicationId);
    }

    public function startSheet(StartSheet $start): void
    {
        $this->authorize('edit', $this->application);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);
        $this->redirectRoute('applications.sheets.show', $start->handle($this->application, $actor));
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->application);
        $this->application->delete();
        $this->redirectRoute('applications.index');
    }

    public function render(OptionCatalog $options, Risks $risks, KnowledgeArticles $articles): View
    {
        $application = $this->application;
        $sheets = Sheet::query()->with(['submitter', 'author', 'approvals.user'])->where('application_id', $application->id)->get();

        return view('applications::livewire.applications.show', [
            'application' => $application,
            'open' => $sheets->first(fn (Sheet $sheet): bool => $sheet->status->isOpen()),
            'current' => $sheets->first(fn (Sheet $sheet): bool => $sheet->status === SheetStatus::Approved),
            // Change history: versions that came into force, newest first.
            'history' => $sheets->filter(fn (Sheet $sheet): bool => $sheet->major !== null)->sortByDesc(fn (Sheet $sheet): array => [$sheet->major, $sheet->minor])->values(),
            'options' => $options,
            'risks' => $risks->isAvailable() && auth()->user() instanceof User ? $risks->concerning('application', $application->id, auth()->user()) : null,
            // Its page in the documentation (CLAUDE.md §9) and other pages about it.
            'articles' => auth()->user() instanceof User ? $articles->about($application->getMorphClass(), $application->id, auth()->user()) : [],
        ])->title($application->name);
    }
}
