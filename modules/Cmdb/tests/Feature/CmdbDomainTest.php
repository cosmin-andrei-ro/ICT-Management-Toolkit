<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Cmdb\Application\Actions\ManageRelationships;
use Modules\Cmdb\Application\Actions\ProvisionDefaultTypes;
use Modules\Cmdb\Application\Actions\SaveConfigurationItem;
use Modules\Cmdb\Application\Impact\ImpactAnalysis;
use Modules\Cmdb\Application\Impact\ImpactedItem;
use Modules\Cmdb\Domain\Enums\CiStatus;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Tests\Support\CmdbScenario;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    $this->cmdb = CmdbScenario::create();
});

/**
 * @param  Illuminate\Support\Collection<int, ImpactedItem>  $impacted
 * @return array<string, int>
 */
function names($impacted): array
{
    return $impacted->mapWithKeys(fn (ImpactedItem $i): array => [$i->item->name => $i->depth])->all();
}

describe('types', function (): void {
    it('gives each organization the default types once, and not again after they were removed', function (): void {
        CiType::query()->forceDelete();

        app(ProvisionDefaultTypes::class)->handle();
        app(ProvisionDefaultTypes::class)->handle();
        expect(CiType::query()->pluck('key')->sort()->values()->all())
            ->toBe(['application', 'business_service', 'cloud_service', 'database', 'network_device', 'server', 'storage', 'virtual_machine']);

        CiType::query()->where('key', 'storage')->sole()->delete();
        app(ProvisionDefaultTypes::class)->handle();
        expect(CiType::query()->where('key', 'storage')->exists())->toBeFalse();
    });

    it('refuses invalid attribute definitions', function (): void {
        CiType::factory()->create(['attribute_schema' => [['key' => 'Bad Key', 'label' => 'x', 'type' => 'text']]]);
    })->throws(InvalidArgumentException::class);
});

describe('items', function (): void {
    beforeEach(function (): void {
        $this->server = CiType::factory()->create(['key' => 'server', 'name' => 'Server', 'attribute_schema' => [
            ['key' => 'operating_system', 'label' => 'Operating system', 'type' => 'text', 'required' => true],
            ['key' => 'memory_gb', 'label' => 'Memory (GB)', 'type' => 'number'],
        ]]);
    });

    it('stores attribute values validated against the type, dropping unknown keys, and audits changes', function (): void {
        $item = app(SaveConfigurationItem::class)->handle(null, $this->server, ['name' => ' srv-db-01 ', 'identifier' => 'SRV-DB-01', 'environment' => 'production', 'owner_id' => $this->cmdb->lead->id],
            ['operating_system' => 'Linux 13', 'memory_gb' => '64', 'injected' => 'x']);

        expect($item->name)->toBe('srv-db-01')
            ->and($item->properties)->toBe(['operating_system' => 'Linux 13', 'memory_gb' => 64])
            ->and(AuditLog::query()->where('auditable_id', $item->id)->exists())->toBeTrue();

        expect(fn () => app(SaveConfigurationItem::class)->handle($item, $this->server, ['name' => 'srv'], ['memory_gb' => 'lots']))
            ->toThrow(ValidationException::class);
    });

    it('refuses owners and types of other organizations', function (): void {
        $outsider = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => User::factory()->create());
        $foreignType = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => CiType::factory()->create());

        expect(fn () => app(SaveConfigurationItem::class)->handle(null, $this->server, ['name' => 'x', 'owner_id' => $outsider->id], ['operating_system' => 'x']))->toThrow(InvalidArgumentException::class)
            ->and(fn () => app(SaveConfigurationItem::class)->handle(null, $foreignType, ['name' => 'x'], []))->toThrow(InvalidArgumentException::class);
    });

    it('keeps identifiers unique in the organization, ignoring case', function (): void {
        $this->cmdb->item('a', ['identifier' => 'SRV-01']);
        $this->cmdb->item('b', ['identifier' => 'srv-01']);
    })->throws(QueryException::class, 'cmdb_configuration_items_identifier_unique');

    it('finds items by name, identifier and attribute values', function (): void {
        $this->server->forceFill(['attribute_schema' => [...$this->server->attribute_schema, ['key' => 'ip_address', 'label' => 'IP address', 'type' => 'text']]])->save();
        $item = app(SaveConfigurationItem::class)->handle(null, $this->server, ['name' => 'payroll-db', 'identifier' => 'SRV-DB-07'], ['operating_system' => 'Windows Server 2025', 'ip_address' => '10.20.30.40']);
        $this->cmdb->item('mail-gateway');

        foreach (['payroll', 'SRV-DB', 'Windows', '10.20.30.40'] as $terms) {
            expect(ConfigurationItem::query()->search($terms)->pluck('id')->all())->toBe([$item->id]);
        }
    });
});

describe('relationships', function (): void {
    it('refuses an item relating to itself, also in the database', function (): void {
        $item = $this->cmdb->item('a');

        expect(fn () => app(ManageRelationships::class)->add($item, RelationshipType::DependsOn, $item))->toThrow(InvalidArgumentException::class);

        DB::table('cmdb_relationships')->insert(['id' => (string) Str::uuid7(), 'organization_id' => $item->organization_id, 'source_id' => $item->id, 'target_id' => $item->id, 'type' => 'uses', 'created_at' => now(), 'updated_at' => now()]);
    })->throws(QueryException::class, 'cmdb_relationships_not_self');

    it('never links items of two organizations, also in the database', function (): void {
        $mine = $this->cmdb->item('mine');
        $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => ConfigurationItem::factory()->create());

        expect(fn () => app(ManageRelationships::class)->add($mine, RelationshipType::Uses, $theirs))->toThrow(InvalidArgumentException::class);

        DB::table('cmdb_relationships')->insert(['id' => (string) Str::uuid7(), 'organization_id' => $mine->organization_id, 'source_id' => $mine->id, 'target_id' => $theirs->id, 'type' => 'uses', 'created_at' => now(), 'updated_at' => now()]);
    })->throws(QueryException::class, 'cmdb_relationships_target_fk');

    it('adds the same relationship once', function (): void {
        [$a, $b] = [$this->cmdb->item('a'), $this->cmdb->item('b')];

        $first = app(ManageRelationships::class)->add($a, RelationshipType::RunsOn, $b);
        $second = app(ManageRelationships::class)->add($a, RelationshipType::RunsOn, $b);

        expect($second->id)->toBe($first->id);
    });
});

describe('impact analysis', function (): void {
    beforeEach(function (): void {
        // payroll (service) depends on payroll-app, which runs on vm-01, which runs on host-01.
        $this->service = $this->cmdb->item('payroll');
        $this->application = $this->cmdb->item('payroll-app');
        $this->vm = $this->cmdb->item('vm-01');
        $this->host = $this->cmdb->item('host-01');
        $this->cmdb->relate($this->service, RelationshipType::DependsOn, $this->application);
        $this->cmdb->relate($this->application, RelationshipType::RunsOn, $this->vm);
        $this->cmdb->relate($this->vm, RelationshipType::RunsOn, $this->host);
    });

    it('finds everything a failure affects, nearest first, and what an item needs', function (): void {
        expect(names(app(ImpactAnalysis::class)->affectedBy([$this->host->id])))->toBe(['vm-01' => 1, 'payroll-app' => 2, 'payroll' => 3])
            ->and(names(app(ImpactAnalysis::class)->dependenciesOf([$this->service->id])))->toBe(['payroll-app' => 1, 'vm-01' => 2, 'host-01' => 3]);
    });

    it('lets a failing part affect the whole, and ignores network links', function (): void {
        $cluster = $this->cmdb->item('cluster');
        $switch = $this->cmdb->item('switch-01');
        $this->cmdb->relate($this->host, RelationshipType::PartOf, $cluster);
        $this->cmdb->relate($this->host, RelationshipType::ConnectsTo, $switch);

        expect(names(app(ImpactAnalysis::class)->affectedBy([$this->host->id])))->toHaveKey('cluster')->not->toHaveKey('switch-01')
            ->and(names(app(ImpactAnalysis::class)->affectedBy([$switch->id])))->toBe([]);
    });

    it('stops at cycles and skips retired items', function (): void {
        $this->cmdb->relate($this->host, RelationshipType::DependsOn, $this->service);
        $this->vm->forceFill(['status' => CiStatus::Retired])->save();

        expect(names(app(ImpactAnalysis::class)->affectedBy([$this->service->id])))->toBe(['host-01' => 1]);
    });

    it('puts the most critical first at the same distance', function (): void {
        $low = $this->cmdb->item('backup-app');
        $critical = $this->cmdb->item('erp', ['criticality' => 'critical']);
        $this->cmdb->relate($low, RelationshipType::RunsOn, $this->host);
        $this->cmdb->relate($critical, RelationshipType::RunsOn, $this->host);

        expect(array_slice(array_keys(names(app(ImpactAnalysis::class)->affectedBy([$this->host->id]))), 0, 1))->toBe(['erp']);
    });
});
