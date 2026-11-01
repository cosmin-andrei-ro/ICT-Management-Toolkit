<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Livewire\Livewire;
use Modules\Core\Application\Authentication\RecordAuthenticationEvent;
use Modules\Core\Application\Authorization\RecordAuthorizationChange;
use Modules\Core\Domain\Audit\RequestContext;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Permission;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Directory\Directory;
use Modules\Core\Domain\Directory\DirectoryGroupMapping;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\Comment;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Middleware\CaptureRequestContext;
use Modules\Core\Http\Middleware\SecurityHeaders;
use Modules\Core\Http\Middleware\SetCurrentOrganization;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Core\Infrastructure\Console\SyncDirectoryCommand;
use Modules\Core\Infrastructure\Console\SyncPermissionsCommand;
use Modules\Core\Infrastructure\Console\TestDirectoryCommand;
use Modules\Core\Infrastructure\Directory\DisabledDirectory;
use Modules\Core\Infrastructure\Directory\LdapConnectionFactory;
use Modules\Core\Infrastructure\Directory\LdapDirectory;
use Modules\Core\Infrastructure\Directory\UnavailableDirectory;
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
        parent::register();

        $this->app->scoped(OrganizationContext::class);
        $this->app->scoped(RequestContext::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(Navigation::class);
        $this->app->scoped(Directory::class, function (Application $app): Directory {
            /** @var array<string, mixed> $config */
            $config = $app->make('config')->get('core.directory', []);

            if (($config['enabled'] ?? false) !== true) {
                return new DisabledDirectory;
            }

            try {
                $connection = LdapConnectionFactory::make($config);
            } catch (InvalidArgumentException $exception) {
                $app->make(ExceptionHandler::class)->report($exception);

                return new UnavailableDirectory($exception->getMessage());
            }

            return new LdapDirectory($connection, is_string($config['base_dn'] ?? null) ? $config['base_dn'] : '');
        });

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(Router::class)->aliasMiddleware('organization', SetCurrentOrganization::class);

        // Livewire update requests do not run route middleware unless it is persistent;
        // without this, components would act with no organization context.
        Livewire::addPersistentMiddleware([SetCurrentOrganization::class]);
        $kernel = $this->app->make(Kernel::class);
        $kernel->prependMiddleware(CaptureRequestContext::class);
        $kernel->pushMiddleware(SecurityHeaders::class);

        // Laravel sorts middleware by priority and would run authentication first. The
        // organization must be known before it: Filament's canAccessPanel() checks an
        // organization-scoped permission. Guests pass through, so this order is safe.
        $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, SetCurrentOrganization::class);

        // Stored in polymorphic columns (audit log, comments, roles): class renames
        // must not orphan history, and every morphable model must be listed.
        Relation::enforceMorphMap([
            'core.organization' => Organization::class,
            'core.user' => User::class,
            'core.department' => Department::class,
            'core.role' => Role::class,
            'core.permission' => Permission::class,
            'core.directory_group_mapping' => DirectoryGroupMapping::class,
            'core.team' => Team::class,
            'core.comment' => Comment::class,
            'core.attachment' => Attachment::class,
        ]);

        Event::listen(
            [RoleAttachedEvent::class, RoleDetachedEvent::class, PermissionAttachedEvent::class, PermissionDetachedEvent::class],
            RecordAuthorizationChange::class,
        );

        Event::listen([Login::class, Failed::class, Logout::class, Lockout::class], RecordAuthenticationEvent::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $config = $this->app->make('config');

            if ($config->get('core.directory.enabled') !== true) {
                return;
            }

            $expression = $config->get('core.directory.sync_schedule');

            $schedule->command(SyncDirectoryCommand::class)
                ->cron(is_string($expression) && $expression !== '' ? $expression : '*/15 * * * *')
                ->withoutOverlapping()
                ->onOneServer();
        });

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('core::layout.admin', 'filament.admin.pages.dashboard', 'filament.admin.*', fn (User $user): bool => $user->can('core.admin.access'));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissionsCommand::class, TestDirectoryCommand::class, SyncDirectoryCommand::class]);
        }
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            'core.admin.access',
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
            'core.directory.manage',
            'core.team.view',
            'core.team.manage',
        ]);

        $directory = ['core.user.view', 'core.department.view', 'core.team.view'];

        $registry->grantToDefaultRole(DefaultRole::TeamLead, [...$directory, 'core.audit.view']);
        $registry->grantToDefaultRole(DefaultRole::Agent, $directory);
        $registry->grantToDefaultRole(DefaultRole::CabMember, $directory);
    }
}
