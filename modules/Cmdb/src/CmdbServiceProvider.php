<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Cmdb\Application\Links\CmdbConfigurationItems;
use Modules\Cmdb\Application\Links\CmdbSearchRecords;
use Modules\Cmdb\Application\PersonalData\CmdbPersonalData;
use Modules\Cmdb\Application\Webhooks\CmdbWebhooks;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Domain\Models\Relationship;
use Modules\Core\Application\Api\ApiAbility;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalData;
use Modules\Core\Domain\Webhooks\WebhookEvents;
use Modules\Core\Http\Navigation\Navigation;

final class CmdbServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'cmdb';
    }

    public function register(): void
    {
        parent::register();
        $this->callAfterResolving(PersonalData::class, fn (PersonalData $data) => $data->register('cmdb::personal_data.sheet', fn (User $person): iterable => app(CmdbPersonalData::class)($person)));
        $this->callAfterResolving(WebhookEvents::class, CmdbWebhooks::register(...));

        $this->app->bind(ConfigurationItems::class, CmdbConfigurationItems::class);

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(LinkedRecords::class, function (LinkedRecords $records): void {
            $records->registerGlobalSearch('cmdb.item', 'cmdb::items.linked_kind', CmdbSearchRecords::items(...));
        });
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
    }

    public function boot(): void
    {
        parent::boot();

        CmdbWebhooks::listen();

        ApiAbility::register('cmdb:read', 'cmdb::api.abilities.read');
        ApiAbility::register('cmdb:write', 'cmdb::api.abilities.write');

        Relation::morphMap([
            'cmdb.ci_type' => CiType::class,
            'cmdb.configuration_item' => ConfigurationItem::class,
            'cmdb.relationship' => Relationship::class,
        ]);

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('cmdb::items.menu', 'cmdb.items.index', 'cmdb.*', fn (User $user): bool => $user->can('cmdb.ci.view'), Navigation::CONFIGURATION);
        });
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            'cmdb.ci.view',
            'cmdb.ci.manage',
            'cmdb.type.manage',
        ]);

        // Owner's decision: agents and team leads keep the CMDB up to date; CAB members read it.
        $registry->grantToDefaultRole(DefaultRole::Agent, ['cmdb.ci.view', 'cmdb.ci.manage']);
        $registry->grantToDefaultRole(DefaultRole::TeamLead, ['cmdb.ci.view', 'cmdb.ci.manage', 'cmdb.type.manage']);
        $registry->grantToDefaultRole(DefaultRole::CabMember, ['cmdb.ci.view']);
        // Asset managers link assets to the items they are.
        $registry->grantToDefaultRole(DefaultRole::AssetManager, ['cmdb.ci.view']);
    }
}
