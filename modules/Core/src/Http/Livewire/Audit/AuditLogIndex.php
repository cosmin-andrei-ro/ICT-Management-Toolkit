<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Audit;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Application\Audit\AuditChanges;
use Modules\Core\Application\Audit\AuditLogQuery;
use Modules\Core\Application\Exports\AuditLogExport;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The audit log of the organization (ADR 0005, 0056): sign-ins, rights,
 * second factors, revealed secrets and exports by default, and every change
 * to records on request. Read-only, as the log itself.
 */
#[Layout('core::components.layouts.app')]
final class AuditLogIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $category = 'security';

    #[Url]
    public string $event = '';

    #[Url]
    public string $person = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $until = '';

    public function mount(): void
    {
        $this->authorize('core.audit.view');
    }

    public function updated(string $property): void
    {
        if ($property === 'category') {
            $this->event = '';
        }

        $this->resetPage();
    }

    public function export(RequestExport $request): void
    {
        $this->authorize('core.audit.view');
        $user = auth()->user();

        if ($user instanceof User) {
            // RequestExport checks the permission and records the export in the audit trail.
            $request->handle($user, AuditLogExport::TYPE, ExportFormat::Xlsx, $this->filters());
        }
    }

    public function render(AuditLogQuery $query, OrganizationContext $context): View
    {
        $this->authorize('core.audit.view');
        /** @var User $user */
        $user = auth()->user();
        $entries = $query->visible($user, $context->requireId(), $this->filters())->with('user:id,name,email')->paginate(50);
        $exports = Export::query()->where('user_id', $user->id)->where('type', AuditLogExport::TYPE)->latest()->limit(3)->get();

        return view('core::livewire.audit.index', [
            'entries' => $entries,
            'changes' => AuditChanges::for($entries->items()),
            'events' => match ($this->category) {
                'security' => AuditEvent::security(),
                'changes' => array_values(array_filter(AuditEvent::cases(), fn (AuditEvent $event): bool => ! $event->isSecurity())),
                default => AuditEvent::cases(),
            },
            'types' => collect(array_keys(Relation::morphMap()))->sort()->mapWithKeys(fn (string $alias): array => [$alias => self::typeLabel($alias)])->all(),
            'exports' => $exports,
            'preparing' => $exports->contains(fn (Export $export): bool => $export->status === ExportStatus::Pending),
            'timezone' => config()->string('app.display_timezone'),
        ])->title(__('core::audit.title'));
    }

    /**
     * "risk.risk_action" → "Risk · Risk action".
     */
    public static function typeLabel(?string $alias): string
    {
        if ($alias === null) {
            return '—';
        }

        [$module, $name] = array_pad(explode('.', $alias, 2), 2, '');

        return Str::headline($module).' · '.Str::ucfirst(str_replace('_', ' ', $name));
    }

    /**
     * @return array{category: string, event: string, person: string, type: string, from: string, until: string}
     */
    private function filters(): array
    {
        $event = AuditEvent::tryFrom($this->event);

        return [
            'category' => in_array($this->category, ['security', 'changes', 'all'], true) ? $this->category : 'security',
            'event' => $event !== null ? $event->value : '',
            'person' => mb_substr(trim($this->person), 0, 200),
            'type' => array_key_exists($this->type, Relation::morphMap()) ? $this->type : '',
            'from' => $this->from,
            'until' => $this->until,
        ];
    }
}
