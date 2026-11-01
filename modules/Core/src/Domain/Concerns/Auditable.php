<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Audit\AuditTrail;

/**
 * Records every create, update, delete and restore in core_audit_logs (ADR 0005).
 *
 * Hidden attributes are never logged. A model can exclude more by defining
 * auditExclude(): list<string>. Mass updates through the query builder bypass
 * model events and are therefore not recorded.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $trail = app(AuditTrail::class);
            $trail->record(AuditEvent::Created, $model, null, $trail->filter($model, $model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $trail = app(AuditTrail::class);
            $changes = $trail->filter($model, $model->getChanges());

            // A soft delete or restore is an update too; it is logged by its own event.
            unset($changes['deleted_at']);

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->getPrevious(), $changes);
            $trail->record(AuditEvent::Updated, $model, $old, $changes);
        });

        static::deleted(function (Model $model): void {
            $trail = app(AuditTrail::class);
            $event = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? AuditEvent::ForceDeleted
                : AuditEvent::Deleted;

            $trail->record($event, $model, $trail->filter($model, $model->getOriginal()), null);
        });

        // registerModelEvent rather than restored(): the latter only exists on soft-deleting models.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::registerModelEvent('restored', function (Model $model): void {
                app(AuditTrail::class)->record(AuditEvent::Restored, $model, null, null);
            });
        }
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
