<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Applications\Application\Options\OptionCatalog;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\OptionValue;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Navigation\Navigation;

final class ApplicationsServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'applications';
    }

    public function register(): void
    {
        parent::register();

        // Scoped: a long-running worker must see values changed later.
        $this->app->scoped(OptionCatalog::class);
        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'applications.application' => Application::class,
            'applications.sheet' => Sheet::class,
            'applications.option_value' => OptionValue::class,
        ]);

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('applications::applications.mine.menu', 'applications.mine', 'applications.mine', fn (User $user): bool => ! $user->can('applications.application.view') && Application::query()->ownedBy($user)->exists(), order: 28);
            $navigation->add('applications::applications.menu', 'applications.index', 'applications.*', fn (User $user): bool => $user->can('applications.application.view'), Navigation::CONFIGURATION);
        });
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            'applications.application.view',
            'applications.application.manage',
            'applications.sheet.approve_security',
            'applications.settings.manage',
        ]);

        $registry->grantToDefaultRole(DefaultRole::ApplicationManager, ['applications.application.view', 'applications.application.manage', 'applications.settings.manage']);
        $registry->grantToDefaultRole(DefaultRole::SecurityOfficer, ['applications.application.view', 'applications.sheet.approve_security']);
        // IT staff read the sheets: they support the applications.
        $registry->grantToDefaultRole(DefaultRole::TeamLead, ['applications.application.view']);
        $registry->grantToDefaultRole(DefaultRole::Agent, ['applications.application.view']);
        $registry->grantToDefaultRole(DefaultRole::ContinuityCoordinator, ['applications.application.view']);
    }
}
