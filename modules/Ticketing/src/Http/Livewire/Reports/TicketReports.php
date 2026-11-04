<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Reports\ReportPeriod;
use Modules\Ticketing\Application\Reports\TicketExport;
use Modules\Ticketing\Application\Reports\TicketReport;

/**
 * Ticketing indicators for a period, and exports of the period's tickets.
 */
#[Layout('core::components.layouts.app')]
final class TicketReports extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $until = '';

    public function mount(): void
    {
        $this->ensureAllowed();

        if ($this->from === '' || $this->until === '') {
            $period = ReportPeriod::lastDays(30, self::timezone());
            $this->from = $period->firstDay;
            $this->until = $period->lastDay;
        }
    }

    public function export(string $format, RequestExport $request): void
    {
        $user = $this->ensureAllowed();
        $format = ExportFormat::tryFrom($format);
        $period = $this->period();

        if ($format === null || $period === null) {
            return;
        }

        $request->handle($user, TicketExport::TYPE, $format, ['from' => $period->firstDay, 'until' => $period->lastDay]);
    }

    public function render(TicketReport $report): View
    {
        $user = $this->ensureAllowed();
        $period = $this->period();

        $exports = Export::query()
            ->where('user_id', $user->id)
            ->where('type', TicketExport::TYPE)
            ->latest()
            ->limit(5)
            ->get();

        return view('ticketing::livewire.reports.tickets', [
            'period' => $period,
            'summary' => $period !== null ? $report->summary($period) : null,
            'sla' => $period !== null ? $report->sla($period) : [],
            'ola' => $period !== null ? $report->ola($period) : [],
            'categories' => $period !== null ? $report->volumeByCategory($period) : collect(),
            'backlog' => $report->backlog(),
            'exports' => $exports,
            'preparing' => $exports->contains(fn (Export $export): bool => $export->status === ExportStatus::Pending),
            'timezone' => self::timezone(),
        ])->title(__('ticketing::reports.title'));
    }

    private function period(): ?ReportPeriod
    {
        $this->resetErrorBag('from');

        try {
            return ReportPeriod::between($this->from, $this->until, self::timezone());
        } catch (InvalidArgumentException) {
            $this->addError('from', __('ticketing::reports.invalid_period', ['days' => ReportPeriod::MAX_DAYS]));

            return null;
        }
    }

    private function ensureAllowed(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can('ticketing.report.view'), 403);

        return $user;
    }

    private static function timezone(): string
    {
        $timezone = config('app.display_timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }
}
