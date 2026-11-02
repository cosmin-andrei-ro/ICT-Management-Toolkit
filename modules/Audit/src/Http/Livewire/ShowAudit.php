<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Audit\Application\Actions\SaveAudit;
use Modules\Audit\Domain\Enums\AuditStatus;
use Modules\Audit\Domain\Models\Audit;

/**
 * One audit: scope, frameworks, status and conclusion, and its findings.
 *
 * @property-read Audit $audit Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowAudit extends Component
{
    #[Locked]
    public string $auditId = '';

    public string $conclusion = '';

    public function mount(Audit $audit): void
    {
        $this->authorize('view', $audit);
        $this->auditId = $audit->id;
        $this->conclusion = $audit->conclusion ?? '';
    }

    #[Computed]
    public function audit(): Audit
    {
        return Audit::query()->with(['leadAuditor', 'frameworks'])->findOrFail($this->auditId);
    }

    public function changeStatus(string $status, SaveAudit $save): void
    {
        $this->authorize('update', $this->audit);
        $this->resetErrorBag();
        $target = AuditStatus::tryFrom($status);

        if ($target === AuditStatus::Completed) {
            $this->validate(['conclusion' => ['required', 'string', 'max:10000']]);
        }

        try {
            $save->changeStatus($this->audit, $target ?? $this->audit->status, $this->conclusion);
        } catch (InvalidArgumentException) {
            $this->addError('conclusion', __('audit::audits.errors.status'));

            return;
        }

        unset($this->audit);
    }

    public function delete(): void
    {
        $audit = $this->audit;
        $this->authorize('delete', $audit);
        $audit->delete();
        $this->redirectRoute('audit.audits.index');
    }

    public function render(): View
    {
        $audit = $this->audit;
        $this->authorize('view', $audit);

        return view('audit::livewire.audits.show', [
            'audit' => $audit,
            'findings' => $audit->findings()->with(['owner', 'control'])->get(),
            'today' => CarbonImmutable::today(),
        ])->title($audit->code().' · '.$audit->title);
    }
}
