<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authorization;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Authorization\Permission;
use Modules\Core\Domain\Authorization\Role;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

/**
 * Role and permission assignments are pivot writes that bypass model observers,
 * so they are logged from the package's events instead.
 */
final readonly class RecordAuthorizationChange
{
    public function __construct(private AuditTrail $trail) {}

    public function handle(RoleAttachedEvent|RoleDetachedEvent|PermissionAttachedEvent|PermissionDetachedEvent $event): void
    {
        [$auditEvent, $key, $model, $items] = match (true) {
            $event instanceof RoleAttachedEvent => [AuditEvent::RoleAssigned, 'roles', Role::class, $event->rolesOrIds],
            $event instanceof RoleDetachedEvent => [AuditEvent::RoleRevoked, 'roles', Role::class, $event->rolesOrIds],
            $event instanceof PermissionAttachedEvent => [AuditEvent::PermissionGranted, 'permissions', Permission::class, $event->permissionsOrIds],
            $event instanceof PermissionDetachedEvent => [AuditEvent::PermissionRevoked, 'permissions', Permission::class, $event->permissionsOrIds],
        };

        $names = $this->names($model, $items);
        $isGrant = in_array($auditEvent, [AuditEvent::RoleAssigned, AuditEvent::PermissionGranted], true);

        $this->trail->record(
            $auditEvent,
            $event->model,
            $isGrant ? null : [$key => $names],
            $isGrant ? [$key => $names] : null,
        );
    }

    /**
     * @param  class-string<Role|Permission>  $model
     * @return list<string>
     */
    private function names(string $model, mixed $rolesOrIds): array
    {
        $items = Collection::wrap($rolesOrIds);
        $ids = $items->map(fn (mixed $item): mixed => $item instanceof $model ? $item->getKey() : $item);

        /** @var list<string> $names */
        $names = $model::query()->whereKey($ids->all())->orderBy('name')->pluck('name')->all();

        return $names;
    }
}
