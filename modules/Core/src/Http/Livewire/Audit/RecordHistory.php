<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Audit;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Application\Audit\AuditChanges;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\User;

/**
 * Who changed what and when on one record, from the audit log (ADR 0005).
 * Those who may change the record see it, and the readers of the audit log
 * (core.audit.view). Checked at every step: a page left open must not keep
 * showing history after someone lost the right. Folded until opened, so a record
 * page does not read its whole history.
 */
final class RecordHistory extends Component
{
    use WithPagination;

    #[Locked]
    public string $type = '';

    #[Locked]
    public string $recordId = '';

    /** What may be done to $changeType (and $changeId) that also allows reading the history. */
    #[Locked]
    public string $ability = 'update';

    #[Locked]
    public string $changeType = '';

    #[Locked]
    public ?string $changeId = null;

    public bool $open = false;

    /**
     * @param  Model|class-string<Model>|null  $target  What the ability applies to; the record itself by default.
     */
    public function mount(Model $record, string $ability = 'update', Model|string|null $target = null): void
    {
        $target ??= $record;
        $this->ability = $ability;
        $this->type = $record->getMorphClass();
        $key = $record->getKey();
        $this->recordId = is_string($key) ? $key : '';

        if ($target instanceof Model) {
            $targetKey = $target->getKey();
            $this->changeType = $target->getMorphClass();
            $this->changeId = is_string($targetKey) ? $targetKey : null;
        } else {
            $this->changeType = $target;
        }

        $this->authorizeReading();
    }

    /**
     * @param  Model|class-string<Model>  $target
     */
    public static function visible(string $ability, Model|string $target): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->can('core.audit.view') || Gate::forUser($user)->allows($ability, $target));
    }

    public function toggle(): void
    {
        $this->authorizeReading();
        $this->open = ! $this->open;
        $this->resetPage('historyPage');
    }

    public function render(): View
    {
        $this->authorizeReading();

        $entries = $this->open
            ? AuditLog::query()->with('user:id,name')->where('auditable_type', $this->type)->where('auditable_id', $this->recordId)
                ->orderByDesc('created_at')->orderByDesc('id')->paginate(20, pageName: 'historyPage')
            : null;

        return view('core::livewire.audit.record-history', [
            'entries' => $entries,
            'changes' => $entries !== null ? AuditChanges::for($entries->items()) : null,
            'timezone' => config()->string('app.display_timezone'),
        ]);
    }

    private function authorizeReading(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        if ($user->can('core.audit.view')) {
            return;
        }

        $class = Relation::getMorphedModel($this->changeType) ?? $this->changeType;
        abort_unless(is_a($class, Model::class, true), 403);

        // Through the model, so that its organization scope applies too.
        $target = $this->changeId !== null ? $class::query()->find($this->changeId) : $class;
        abort_unless($target !== null && Gate::forUser($user)->allows($this->ability, $target), 403);
    }
}
