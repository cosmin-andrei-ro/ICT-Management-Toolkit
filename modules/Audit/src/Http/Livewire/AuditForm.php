<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Audit\Application\Actions\SaveAudit;
use Modules\Audit\Domain\Enums\AuditKind;
use Modules\Audit\Domain\Models\Audit;
use Modules\Audit\Domain\Models\Framework;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Plans an audit or changes it: kind, scope, frameworks, lead auditor, dates.
 */
#[Layout('core::components.layouts.app')]
final class AuditForm extends Component
{
    #[Locked]
    public ?string $auditId = null;

    public string $title = '';

    public string $kind = 'internal';

    public string $scope = '';

    public string $leadAuditorId = '';

    public string $auditBody = '';

    public string $startsOn = '';

    public string $endsOn = '';

    /** @var list<string> */
    public array $frameworkIds = [];

    public function mount(?Audit $audit = null): void
    {
        if ($audit === null || ! $audit->exists) {
            $this->authorize('create', Audit::class);
            $this->startsOn = CarbonImmutable::today()->toDateString();

            return;
        }

        $this->authorize('update', $audit);
        $this->auditId = $audit->id;
        $this->title = $audit->title;
        $this->kind = $audit->kind->value;
        $this->scope = $audit->scope ?? '';
        $this->leadAuditorId = $audit->lead_auditor_id ?? '';
        $this->auditBody = $audit->audit_body ?? '';
        $this->startsOn = $audit->starts_on->toDateString();
        $this->endsOn = $audit->ends_on?->toDateString() ?? '';
        $this->frameworkIds = array_values(array_filter($audit->frameworks()->pluck('audit_frameworks.id')->all(), is_string(...)));
    }

    public function save(SaveAudit $save, OrganizationContext $context): void
    {
        $audit = $this->auditId !== null ? Audit::query()->findOrFail($this->auditId) : null;
        $this->authorize($audit !== null ? 'update' : 'create', $audit ?? Audit::class);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(AuditKind::class)],
            'scope' => ['nullable', 'string', 'max:10000'],
            'leadAuditorId' => ['nullable', 'uuid', Rule::in($this->people($context)->pluck('id')->all())],
            'auditBody' => ['nullable', 'string', 'max:255', 'required_if:kind,external'],
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'endsOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startsOn'],
            'frameworkIds' => ['array', 'max:50'],
            'frameworkIds.*' => ['uuid'],
        ]);

        $saved = $save->handle($audit, [
            'title' => $this->title,
            'kind' => AuditKind::from($this->kind),
            'scope' => $this->scope,
            'lead_auditor_id' => $this->leadAuditorId !== '' ? $this->leadAuditorId : null,
            'audit_body' => $this->auditBody,
            'starts_on' => CarbonImmutable::createFromFormat('!Y-m-d', $this->startsOn) ?: CarbonImmutable::today(),
            'ends_on' => $this->endsOn !== '' ? (CarbonImmutable::createFromFormat('!Y-m-d', $this->endsOn) ?: null) : null,
        ], array_values(array_filter($this->frameworkIds, is_string(...))), $this->actor());

        $this->redirectRoute('audit.audits.show', $saved);
    }

    public function render(OrganizationContext $context): View
    {
        return view('audit::livewire.audits.form', [
            'people' => $this->people($context),
            'kinds' => AuditKind::cases(),
            'frameworks' => Framework::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ])->title(__($this->auditId !== null ? 'audit::audits.ui.edit' : 'audit::audits.ui.new'));
    }

    /**
     * @return Collection<int, User>
     */
    private function people(OrganizationContext $context): Collection
    {
        return User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
