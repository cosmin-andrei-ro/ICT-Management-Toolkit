<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Modules\Core\Application\Authorization\RecordAuthorizationChange;
use Modules\Core\Domain\Audit\RequestContext;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Permission;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Middleware\CaptureRequestContext;
use Modules\Core\Http\Middleware\SetCurrentOrganization;
use Modules\Core\Infrastructure\Console\SyncPermissionsCommand;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

final class CoreServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'core';
    }

    public function register(): void
    {
        $this->app->scoped(OrganizationContext::class);
        $this->app->scoped(RequestContext::class);
        $this->app->singleton(PermissionRegistry::class);

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(Router::class)->aliasMiddleware('organization', SetCurrentOrganization::class);
        $this->app->make(Kernel::class)->prependMiddleware(CaptureRequestContext::class);

        // Stored in polymorphic columns (audit log, comments, roles): class renames
        // must not orphan history, and every morphable model must be listed.
        Relation::enforceMorphMap([
            'core.organization' => Organization::class,
            'core.user' => User::class,
            'core.department' => Department::class,
            'core.role' => Role::class,
            'core.permission' => Permission::class,
        ]);

        Event::listen(
            [RoleAttachedEvent::class, RoleDetachedEvent::class, PermissionAttachedEvent::class, PermissionDetachedEvent::class],
            RecordAuthorizationChange::class,
        );

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissionsCommand::class]);
        }
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            'core.organization.view',
            'core.organization.update',
            'core.user.view',
            'core.user.create',
            'core.user.update',
            'core.user.deactivate',
            'core.role.view',
            'core.role.manage',
            'core.department.view',
            'core.department.create',
            'core.department.update',
            'core.department.delete',
            'core.audit.view',
        ]);

        $directory = ['core.user.view', 'core.department.view'];

        $registry->grantToDefaultRole(DefaultRole::TeamLead, [...$directory, 'core.audit.view']);
        $registry->grantToDefaultRole(DefaultRole::Agent, $directory);
        $registry->grantToDefaultRole(DefaultRole::CabMember, $directory);
    }
}
