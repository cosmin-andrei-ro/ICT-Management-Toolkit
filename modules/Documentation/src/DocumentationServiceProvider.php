<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Events\ApplicationDocumented;
use Modules\Core\Domain\Links\KnowledgeArticles;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalData;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Documentation\Application\Links\DocumentationArticles;
use Modules\Documentation\Application\Listeners\GenerateApplicationPage;
use Modules\Documentation\Application\Metrics\DocumentationMetrics;
use Modules\Documentation\Application\PersonalData\DocumentationPersonalData;
use Modules\Documentation\Application\SpaceAccess;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\Space;
use Modules\Documentation\Infrastructure\Console\SendDocumentationDigestCommand;

final class DocumentationServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'documentation';
    }

    public function register(): void
    {
        parent::register();
        $this->callAfterResolving(PersonalData::class, fn (PersonalData $data) => $data->register('documentation::personal_data.sheet', fn (User $person): iterable => app(DocumentationPersonalData::class)($person)));

        $this->callAfterResolving(Metrics::class, fn (Metrics $metrics) => $metrics->register(DocumentationMetrics::source()));

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
        $this->app->bind(KnowledgeArticles::class, DocumentationArticles::class);
        // Other modules link documentation pages (e.g. a procedure as evidence of a control).
        $this->callAfterResolving(LinkedRecords::class, function (LinkedRecords $records): void {
            $records->register('documentation.page', fn (array $ids, User $viewer): array => app(DocumentationArticles::class)->resolve($ids, $viewer));
            $records->registerSearch('documentation.page', 'documentation::docs.linked_kind', fn (string $term, User $viewer): array => app(DocumentationArticles::class)->search($term, $viewer));
        });
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(ApplicationDocumented::class, GenerateApplicationPage::class);

        Relation::morphMap([
            'documentation.space' => Space::class,
            'documentation.page' => Page::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendDocumentationDigestCommand::class)->weeklyOn(1, '08:20')->withoutOverlapping()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SendDocumentationDigestCommand::class]);
        }

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('documentation::docs.menu', 'documentation.index', 'documentation.*', fn (User $user): bool => $user->can('documentation.staff.read')
                || Space::query()->tap(fn (Builder $query) => app(SpaceAccess::class)->readable($query, $user))->exists(), order: 35);
        });
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            // Spaces for IT staff (visibility "staff").
            'documentation.staff.read',
            // Every space: create, set readers, editors and owners.
            'documentation.space.manage',
        ]);

        foreach ([DefaultRole::Agent, DefaultRole::TeamLead, DefaultRole::CabMember, DefaultRole::AssetManager, DefaultRole::ContinuityCoordinator, DefaultRole::ApplicationManager, DefaultRole::SecurityOfficer, DefaultRole::RiskManager, DefaultRole::ComplianceManager, DefaultRole::Auditor] as $role) {
            $registry->grantToDefaultRole($role, ['documentation.staff.read']);
        }

        $registry->grantToDefaultRole(DefaultRole::TeamLead, ['documentation.space.manage']);
    }
}
