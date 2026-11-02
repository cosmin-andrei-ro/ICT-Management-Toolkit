<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Http\Livewire\SheetPage;
use Modules\Applications\Http\Livewire\ShowApplication;
use Modules\Applications\Tests\Support\ApplicationsScenario;
use Modules\Cmdb\Domain\Models\CiLink;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Http\Livewire\ShowItem;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    Notification::fake();
    $this->apps = ApplicationsScenario::create();
    $this->actingAs($this->apps->technicalOwner);
    Livewire::test(ShowApplication::class, ['application' => $this->apps->ticketing])->call('startSheet');
    $this->sheet = Sheet::query()->sole();
});

it('draws the network diagram from the firewall rules', function (): void {
    $page = Livewire::test(SheetPage::class, ['sheet' => $this->sheet])->call('openChapter', 4)
        ->assertSee('Add the firewall rules in 4.3');

    foreach ([['Users (VLAN 100)', 'SD-LB-VIP', '443'], ['SD-LB-VIP', 'SDAPP01', '8443'], ['SDAPP01', 'SDDB01', '5432']] as $index => [$source, $destination, $port]) {
        $page->call('addRow', '4_3', 'rules')
            ->set("form.4_3.rules.{$index}.source", $source)->set("form.4_3.rules.{$index}.destination", $destination)
            ->set("form.4_3.rules.{$index}.port", $port)->set("form.4_3.rules.{$index}.protocol", 'https');
    }

    $page->set('form.4_1.tls_termination', 'load_balancer')->call('save')->assertHasNoErrors()
        ->assertSeeHtml('<title id="network-title">Network diagram of Ticketing Platform</title>')
        ->assertSee('SDDB01')->assertSee('8443 HTTPS');

    expect($this->sheet->fresh()->section('4_3')['rules'])->toHaveCount(3);
});

it('links CMDB items to the version, lists the application on the item and keeps the links in the next version', function (): void {
    $type = CiType::factory()->create(['key' => 'server']);
    $server = ConfigurationItem::factory()->create(['type_id' => $type->id, 'name' => 'SDAPP01']);
    app(OrganizationContext::class)->set(Organization::factory()->create());
    $foreign = ConfigurationItem::factory()->create(['type_id' => CiType::factory()->create()->id, 'name' => 'SDAPP99']);
    app(OrganizationContext::class)->set($this->apps->organization);

    Livewire::test(SheetPage::class, ['sheet' => $this->sheet])->call('openChapter', 5)
        ->set('itemSearch', 'SDAPP01')->assertSee('SDAPP01')->set('itemSearch', 'SDAPP99')->assertDontSee('SDAPP99')
        ->call('linkItem', $server->id)->assertHasNoErrors()
        ->call('linkItem', $foreign->id)->assertHasErrors('itemSearch')
        ->assertSee('SDAPP01');

    $this->actingAs($this->apps->agent);
    Livewire::test(ShowItem::class, ['item' => $server])->assertSee('Application: Ticketing Platform (APP-TKT)');
    Livewire::test(SheetPage::class, ['sheet' => $this->sheet])->call('openChapter', 5)->call('linkItem', $server->id)->assertForbidden();

    // In force, then a new version starts from the same links.
    $this->sheet->forceFill(['status' => 'approved', 'major' => 1, 'minor' => 0, 'approved_at' => now()])->save();
    $this->actingAs($this->apps->technicalOwner);
    Livewire::test(ShowApplication::class, ['application' => $this->apps->ticketing])->call('startSheet');
    $next = Sheet::query()->where('status', 'draft')->sole();
    expect(CiLink::query()->where('linkable_type', 'applications.sheet')->where('linkable_id', $next->id)->pluck('configuration_item_id')->all())->toBe([$server->id]);
});

it('records software, databases, libraries and technical accounts', function (): void {
    Livewire::test(SheetPage::class, ['sheet' => $this->sheet])->call('openChapter', 6)
        ->set('form.6_1.application_type', 'web')
        ->call('addRow', '6_4', 'databases')->set('form.6_4.databases.0.dbms', 'postgresql')->set('form.6_4.databases.0.version', '16')
        ->call('addRow', '6_6', 'libraries')->set('form.6_6.libraries.0.name', 'Laravel Framework')->set('form.6_6.libraries.0.support_until', '31.12.2026')
        ->call('save')->assertHasErrors('form.6_6.libraries.0.support_until')
        ->set('form.6_6.libraries.0.support_until', '2026-12-31')
        ->call('openChapter', 7)->assertHasNoErrors()
        ->call('addRow', '7', 'accounts')->set('form.7.accounts.0.username', 'svc_sd_db')->set('form.7.accounts.0.credential_location', 'CyberArk safe SD-PROD')
        ->call('save')->assertHasNoErrors();

    $sheet = $this->sheet->fresh();
    expect($sheet->section('6_4')['databases'][0]['dbms'])->toBe('postgresql')
        ->and($sheet->section('6_6')['libraries'][0]['support_until'])->toBe('2026-12-31')
        ->and($sheet->section('7')['accounts'][0]['credential_location'])->toBe('CyberArk safe SD-PROD');

    $this->actingAs($this->apps->agent);
    Livewire::test(SheetPage::class, ['sheet' => $sheet])->call('openChapter', 6)->assertSee('PostgreSQL')->assertSee('Laravel Framework')
        ->call('openChapter', 7)->assertSee('svc_sd_db')->assertSee('Credential location (vault, never the password)');
});
