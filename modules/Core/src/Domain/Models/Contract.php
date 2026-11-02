<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Policies\ContractPolicy;
use Modules\Core\Database\Factories\ContractFactory;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\HasAttachments;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Enums\BillingPeriod;
use Modules\Core\Domain\Enums\ContractStatus;
use Modules\Core\Domain\Enums\ContractType;

/**
 * An agreement with a supplier: support, licences, a lease… Its deadline (end
 * date minus notice period) is the last day to cancel or renegotiate; reminders
 * are sent before it. Written through SaveContract.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $supplier_id
 * @property string $title
 * @property ?string $reference
 * @property ContractType $type
 * @property CarbonImmutable $start_date
 * @property ?CarbonImmutable $end_date
 * @property bool $auto_renews
 * @property ?int $renewal_months
 * @property ?int $notice_days
 * @property ?CarbonImmutable $terminated_on
 * @property ?string $value Decimal, as a string to keep it exact.
 * @property ?string $currency
 * @property ?BillingPeriod $billing_period
 * @property ?string $owner_id
 * @property ?string $description
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read Supplier $supplier
 * @property-read ?User $owner
 */
#[Table('core_contracts')]
#[UseFactory(ContractFactory::class)]
#[UsePolicy(ContractPolicy::class)]
#[Fillable(['supplier_id', 'title', 'reference', 'type', 'start_date', 'end_date', 'auto_renews', 'renewal_months', 'notice_days', 'terminated_on', 'value', 'currency', 'billing_period', 'owner_id', 'description'])]
final class Contract extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'auto_renews' => false,
    ];

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Days before a deadline when reminders go out (config core.contracts.reminder_days), largest first.
     *
     * @return list<int>
     */
    public static function reminderDays(): array
    {
        $days = config('core.contracts.reminder_days');
        $days = is_array($days) ? array_values(array_filter($days, fn (mixed $value): bool => is_int($value) && $value > 0)) : [];
        rsort($days);

        return $days;
    }

    /**
     * How close to its deadline a contract counts as expiring: from the first reminder on.
     */
    public static function expiringDays(): int
    {
        return self::reminderDays()[0] ?? 30;
    }

    /**
     * The last day to cancel or renegotiate; null for a contract without an end.
     */
    public function deadline(): ?CarbonImmutable
    {
        return $this->end_date?->subDays($this->notice_days ?? 0);
    }

    /**
     * @param  int  $expiringDays  How close to the deadline a contract counts as expiring.
     */
    public function statusOn(CarbonImmutable $today, int $expiringDays): ContractStatus
    {
        $today = $today->startOfDay();
        $deadline = $this->deadline();

        return match (true) {
            $this->terminated_on !== null && $this->terminated_on <= $today => ContractStatus::Terminated,
            $this->start_date > $today => ContractStatus::Upcoming,
            $this->end_date !== null && $this->end_date < $today => ContractStatus::Expired,
            $deadline !== null && $deadline->subDays($expiringDays) <= $today => ContractStatus::Expiring,
            default => ContractStatus::Active,
        };
    }

    /**
     * The same rules as statusOn(), in SQL, for filtering lists.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithStatus(Builder $query, ContractStatus $status, CarbonImmutable $today, int $expiringDays): void
    {
        $day = $today->toDateString();
        $notTerminated = fn (Builder $inner) => $inner->whereNull('terminated_on')->orWhere('terminated_on', '>', $day);
        $started = fn (Builder $inner) => $inner->where($notTerminated)->where('start_date', '<=', $day);
        $running = fn (Builder $inner) => $inner->where($started)->where(fn (Builder $end) => $end->whereNull('end_date')->orWhere('end_date', '>=', $day));
        $nearDeadline = 'end_date - COALESCE(notice_days, 0) - ?::integer <= ?::date';

        match ($status) {
            ContractStatus::Terminated => $query->where('terminated_on', '<=', $day),
            ContractStatus::Upcoming => $query->where($notTerminated)->where('start_date', '>', $day),
            ContractStatus::Expired => $query->where($started)->where('end_date', '<', $day),
            ContractStatus::Expiring => $query->where($running)->whereNotNull('end_date')->whereRaw($nearDeadline, [$expiringDays, $day]),
            ContractStatus::Active => $query->where($running)->where(fn (Builder $inner) => $inner
                ->whereNull('end_date')
                ->orWhereRaw('NOT ('.$nearDeadline.')', [$expiringDays, $day])),
        };
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, string $terms): void
    {
        $terms = trim($terms);

        if ($terms === '') {
            return;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $terms).'%';
        $query->where(fn (Builder $inner) => $inner
            ->whereLike('title', $like)
            ->orWhereLike('reference', $like)
            ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->whereLike('name', $like)));
    }

    protected function casts(): array
    {
        return [
            'type' => ContractType::class,
            'billing_period' => BillingPeriod::class,
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'terminated_on' => 'immutable_date',
            'auto_renews' => 'boolean',
            'renewal_months' => 'integer',
            'notice_days' => 'integer',
            'value' => 'decimal:2',
        ];
    }
}
