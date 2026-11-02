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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
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
use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\ContractDependents;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Links\NoBusinessProcesses;
use Modules\Core\Domain\Links\NoConfigurationItems;
use Modules\Core\Domain\Links\NoServiceDeskTickets;
use Modules\Core\Domain\Links\ServiceDeskTickets;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\BusinessCalendar;
use Modules\Core\Domain\Models\Comment;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\SupplierContact;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Middleware\CaptureRequestContext;
use Modules\Core\Http\Middleware\RequireTwoFactor;
use Modules\Core\Http\Middleware\SecurityHeaders;
use Modules\Core\Http\Middleware\SetCurrentOrganization;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Core\Infrastructure\Console\IssueApiTokenCommand;
use Modules\Core\Infrastructure\Console\PruneExportsCommand;
use Modules\Core\Infrastructure\Console\SendContractRemindersCommand;
use Modules\Core\Infrastructure\Console\SetupCommand;
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
        $this->app->singleton(Manual::class);
        $this->app->singleton(ExportRegistry::class);
        $this->app->singleton(LinkedRecords::class);
        $this->app->singleton(ContractDependents::class);
        // Replaced by the CMDB module when it is enabled.
        $this->app->bindIf(ConfigurationItems::class, NoConfigurationItems::class);
        // Replaced by the BIA module when it is enabled.
        $this->app->bindIf(BusinessProcesses::class, NoBusinessProcesses::class);
        // Replaced by the Ticketing module when it is enabled.
        $this->app->bindIf(ServiceDeskTickets::class, NoServiceDeskTickets::class);
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
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

        $proxies = $this->app->make('config')->get('core.http.trusted_proxies');

        if (is_array($proxies) && $proxies !== []) {
            TrustProxies::at(array_values(array_filter($proxies, 'is_string')));
        }

        // NIST SP 800-63B: length over composition rules; long passphrases allowed.
        Password::defaults(fn (): Password => Password::min(12)->max(128));

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('organization', SetCurrentOrganization::class);
        $router->aliasMiddleware('mfa', RequireTwoFactor::class);
        $router->aliasMiddleware('abilities', CheckAbilities::class);
        $router->aliasMiddleware('ability', CheckForAnyAbility::class);

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // OpenAPI docs (Scramble): administrators only outside the local environment.
        Gate::define('viewApiDocs', fn (User $user): bool => $user->can('core.admin.access'));

        // Livewire update requests do not run route middleware unless it is persistent;
        // without this, components would act with no organization context.
        Livewire::addPersistentMiddleware([SetCurrentOrganization::class, AuthenticateSession::class, RequireTwoFactor::class]);
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
            'core.business_calendar' => BusinessCalendar::class,
            'core.export' => Export::class,
            'core.location' => Location::class,
            'core.supplier' => Supplier::class,
            'core.supplier_contact' => SupplierContact::class,
            'core.contract' => Contract::class,
        ]);

        Event::listen(
            [RoleAttachedEvent::class, RoleDetachedEvent::class, PermissionAttachedEvent::class, PermissionDetachedEvent::class],
            RecordAuthorizationChange::class,
        );

        Event::listen([Login::class, Failed::class, Logout::class, Lockout::class], RecordAuthenticationEvent::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PruneExportsCommand::class)->hourly()->onOneServer();
            $schedule->command(SendContractRemindersCommand::class)->dailyAt('07:00')->withoutOverlapping()->onOneServer();
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
            $navigation->add('core::suppliers.menu', 'core.suppliers.index', 'core.suppliers.*', fn (User $user): bool => $user->can('core.supplier.view'), Navigation::SUPPLIERS);
            $navigation->add('core::contracts.menu', 'core.contracts.index', 'core.contracts.*', fn (User $user): bool => $user->can('core.contract.view'), Navigation::SUPPLIERS);
            $navigation->add('core::layout.admin', 'filament.admin.pages.dashboard', 'filament.admin.*', fn (User $user): bool => $user->can('core.admin.access'), order: 90);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissionsCommand::class, TestDirectoryCommand::class, SyncDirectoryCommand::class, IssueApiTokenCommand::class, PruneExportsCommand::class, SetupCommand::class, SendContractRemindersCommand::class]);
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
            'core.calendar.manage',
            'core.location.manage',
            'core.supplier.view',
            'core.supplier.manage',
            // Contracts show values and terms: not every agent sees them (owner's decision).
            'core.contract.view',
            'core.contract.manage',
        ]);

        $directory = ['core.user.view', 'core.department.view', 'core.team.view'];
        $contracts = ['core.location.manage', 'core.supplier.view', 'core.supplier.manage', 'core.contract.view', 'core.contract.manage'];

        // Panel access only opens the door: every page and resource in it checks its own permission.
        $registry->grantToDefaultRole(DefaultRole::TeamLead, [...$directory, 'core.audit.view', 'core.admin.access', ...$contracts]);
        $registry->grantToDefaultRole(DefaultRole::AssetManager, [...$directory, 'core.admin.access', ...$contracts]);
        // The continuity coordinator keeps the organization chart processes hang from, and reads suppliers and locations for dependencies.
        $registry->grantToDefaultRole(DefaultRole::ContinuityCoordinator, [...$directory, 'core.admin.access', 'core.department.create', 'core.department.update', 'core.supplier.view', 'core.location.manage']);
        // Application managers open the panel for the sheet's dropdowns and read suppliers and contracts for vendor support.
        $registry->grantToDefaultRole(DefaultRole::ApplicationManager, [...$directory, 'core.admin.access', 'core.supplier.view', 'core.contract.view']);
        $registry->grantToDefaultRole(DefaultRole::SecurityOfficer, [...$directory, 'core.audit.view']);
        $registry->grantToDefaultRole(DefaultRole::Agent, [...$directory, 'core.supplier.view']);
        $registry->grantToDefaultRole(DefaultRole::CabMember, $directory);
    }
}
