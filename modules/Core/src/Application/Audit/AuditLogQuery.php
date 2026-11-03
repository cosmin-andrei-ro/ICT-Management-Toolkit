<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Audit;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\User;

/**
 * The audit log entries a reader of an organization may see. The log has no
 * organization scope (ADR 0005): sign-ins and changes to people are recorded
 * without an organization, so they are matched through the organization's
 * members. Failed sign-ins for unknown accounts belong to no organization and
 * are shown to administrators only.
 */
final readonly class AuditLogQuery
{
    /**
     * @param  array{category?: string, event?: string, person?: string, type?: string, from?: string, until?: string}  $filters
     * @return Builder<AuditLog>
     */
    public function visible(User $reader, string $organizationId, array $filters = []): Builder
    {
        $members = fn (QueryBuilder $query) => $query->select('user_id')->from('core_organization_user')->where('organization_id', $organizationId);
        $administrator = $reader->hasRole(DefaultRole::Administrator->value);

        $query = AuditLog::query()->where(function (Builder $query) use ($organizationId, $members, $administrator): void {
            $query->where('organization_id', $organizationId)
                ->orWhere(fn (Builder $query) => $query->whereNull('organization_id')->where(fn (Builder $query) => $query
                    ->whereIn('user_id', $members)
                    ->orWhere(fn (Builder $query) => $query->where('auditable_type', 'core.user')->whereIn('auditable_id', $members))
                    ->when($administrator, fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query->whereNull('user_id')->whereNull('auditable_id')))));
        });

        $category = $filters['category'] ?? '';
        $event = AuditEvent::tryFrom($filters['event'] ?? '');

        if ($event !== null) {
            $query->where('event', $event->value);
        } elseif ($category === 'security') {
            $query->whereIn('event', array_map(fn (AuditEvent $event): string => $event->value, AuditEvent::security()));
        } elseif ($category === 'changes') {
            $query->whereNotIn('event', array_map(fn (AuditEvent $event): string => $event->value, AuditEvent::security()));
        }

        $person = trim($filters['person'] ?? '');

        if ($person !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($person, 0, 200)).'%';
            $query->whereIn('user_id', User::query()->select('id')->where(fn (Builder $query) => $query->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like)));
        }

        $type = trim($filters['type'] ?? '');

        if ($type !== '') {
            $query->where('auditable_type', $type);
        }

        foreach (['from' => '>=', 'until' => '<'] as $key => $operator) {
            $date = self::date($filters[$key] ?? '');

            if ($date !== null) {
                $query->where('created_at', $operator, $key === 'until' ? $date->addDay() : $date);
            }
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    private static function date(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $timezone = config()->string('app.display_timezone');
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);

        return $date instanceof CarbonImmutable ? $date->utc() : null;
    }
}
