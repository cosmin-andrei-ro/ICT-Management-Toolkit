<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Audit;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Application\Audit\AuditChanges;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\User;

/**
 * Who changed what and when on one record, from the audit log (ADR 0005).
 * The page showing the record decides who sees it: the people who may change
 * the record, and the readers of the audit log (core.audit.view). Folded
 * until opened, so a record page does not read its whole history.
 */
final class RecordHistory extends Component
{
    use WithPagination;

    #[Locked]
    public string $type = '';

    #[Locked]
    public string $recordId = '';

    public bool $open = false;

    /**
     * @param  bool  $canChange  Whether the reader may change the record, as the page knows it.
     */
    public function mount(Model $record, bool $canChange): void
    {
        abort_unless(self::visible($canChange), 403);
        $this->type = $record->getMorphClass();
        $key = $record->getKey();
        $this->recordId = is_string($key) ? $key : '';
    }

    public static function visible(bool $canChange): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($canChange || $user->can('core.audit.view'));
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
        $this->resetPage('historyPage');
    }

    public function render(): View
    {
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
}
