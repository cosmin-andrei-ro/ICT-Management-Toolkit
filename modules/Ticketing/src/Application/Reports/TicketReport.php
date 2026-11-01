<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\SlaTimer;
use Modules\Ticketing\Domain\Models\StatusTransition;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Domain\Sla\SlaMetric;
use Modules\Ticketing\Domain\Sla\SlaOutcome;

/**
 * Service desk indicators for the current organization: incidents and service
 * requests only (problems and changes are measured differently). Aggregated in
 * the database, so the cost does not grow with the number of tickets loaded.
 */
final readonly class TicketReport
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * @return array{created: int, resolved: int, average_hours: ?float, median_hours: ?float, reopened: int, reopen_rate: ?float}
     */
    public function summary(ReportPeriod $period): array
    {
        $created = $this->tickets()->where('created_at', '>=', $period->start)->where('created_at', '<', $period->end)->count();

        $resolution = $this->tickets()
            ->where('resolved_at', '>=', $period->start)
            ->where('resolved_at', '<', $period->end)
            ->toBase()
            ->selectRaw('count(*) as resolved')
            ->selectRaw('avg(extract(epoch from resolved_at - created_at)) as average_seconds')
            ->selectRaw('percentile_cont(0.5) within group (order by extract(epoch from resolved_at - created_at)) as median_seconds')
            ->first();

        $resolvedInPeriod = $this->transitions($period)->where('to_status', TicketStatus::Resolved)->distinct()->count('ticket_id');
        $reopened = $this->transitions($period)
            ->where('from_status', TicketStatus::Resolved)
            ->whereIn('to_status', TicketStatus::open())
            ->distinct()
            ->count('ticket_id');

        return [
            'created' => $created,
            'resolved' => self::count($resolution->resolved ?? null),
            'average_hours' => self::hours($resolution->average_seconds ?? null),
            'median_hours' => self::hours($resolution->median_seconds ?? null),
            'reopened' => $reopened,
            'reopen_rate' => $resolvedInPeriod > 0 ? round(100 * $reopened / $resolvedInPeriod, 1) : null,
        ];
    }

    /**
     * Clocks that completed in the period; cancelled ones are not counted.
     *
     * @return list<array{metric: SlaMetric, met: int, breached: int, compliance: ?float}>
     */
    public function sla(ReportPeriod $period): array
    {
        $counts = SlaTimer::query()
            ->where('completed_at', '>=', $period->start)
            ->where('completed_at', '<', $period->end)
            ->whereIn('outcome', [SlaOutcome::Met, SlaOutcome::Breached])
            ->toBase()
            ->select(['metric', 'outcome'])
            ->selectRaw('count(*) as total')
            ->groupBy('metric', 'outcome')
            ->get();

        $rows = [];

        foreach (SlaMetric::cases() as $metric) {
            $met = self::count($counts->first(fn (object $row): bool => $row->metric === $metric->value && $row->outcome === SlaOutcome::Met->value)->total ?? null);
            $breached = self::count($counts->first(fn (object $row): bool => $row->metric === $metric->value && $row->outcome === SlaOutcome::Breached->value)->total ?? null);

            $rows[] = [
                'metric' => $metric,
                'met' => $met,
                'breached' => $breached,
                'compliance' => $met + $breached > 0 ? round(100 * $met / ($met + $breached), 1) : null,
            ];
        }

        return $rows;
    }

    /**
     * Open tickets now, per queue, with the oldest one's creation time.
     *
     * @return Collection<int, array{queue: ?string, open: int, oldest: ?CarbonImmutable}>
     */
    public function backlog(): Collection
    {
        $rows = $this->tickets()
            ->whereIn('status', TicketStatus::open())
            ->toBase()
            ->select('queue_id')
            ->selectRaw('count(*) as open, min(created_at) as oldest')
            ->groupBy('queue_id')
            ->get();

        $names = Queue::query()->withTrashed()->whereKey($rows->pluck('queue_id')->filter()->all())->pluck('name', 'id');

        return $rows
            ->map(fn (object $row): array => [
                'queue' => is_string($row->queue_id) && is_string($names->get($row->queue_id)) ? $names->get($row->queue_id) : null,
                'open' => self::count($row->open),
                'oldest' => is_string($row->oldest) ? CarbonImmutable::parse($row->oldest) : null,
            ])
            ->sortByDesc('open')
            ->values();
    }

    /**
     * Tickets created in the period, per category.
     *
     * @return Collection<int, array{category: ?string, created: int}>
     */
    public function volumeByCategory(ReportPeriod $period): Collection
    {
        $rows = $this->tickets()
            ->where('created_at', '>=', $period->start)
            ->where('created_at', '<', $period->end)
            ->toBase()
            ->select('category_id')
            ->selectRaw('count(*) as created')
            ->groupBy('category_id')
            ->get();

        $categories = Category::query()->withTrashed()->with('parent')->whereKey($rows->pluck('category_id')->filter()->all())->get()->keyBy('id');

        return $rows
            ->map(fn (object $row): array => [
                'category' => is_string($row->category_id) ? $categories->get($row->category_id)?->fullName() : null,
                'created' => self::count($row->created),
            ])
            ->sortByDesc('created')
            ->values();
    }

    /**
     * @return Builder<Ticket>
     */
    public function tickets(): Builder
    {
        return Ticket::query()->whereIn('type', TicketType::requestable());
    }

    /**
     * @return Builder<StatusTransition>
     */
    private function transitions(ReportPeriod $period): Builder
    {
        // Transitions carry no organization scope: filter explicitly, and through the scoped ticket query.
        return StatusTransition::query()
            ->where('organization_id', $this->context->requireId())
            ->whereIn('ticket_id', $this->tickets()->select('id'))
            ->where('created_at', '>=', $period->start)
            ->where('created_at', '<', $period->end);
    }

    private static function count(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function hours(mixed $seconds): ?float
    {
        return is_numeric($seconds) ? round((float) $seconds / 3600, 1) : null;
    }
}
