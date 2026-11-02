<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Cmdb\Domain\Models\CiLink;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Http\Livewire\ShowItem;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Livewire\Contracts\ShowContract;
use Modules\Itam\Application\Actions\AssignAsset;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\Category;
use Modules\Itam\Http\Filament\Resources\Categories\Pages\ManageCategories;
use Modules\Itam\Http\Livewire\AssetForm;
use Modules\Itam\Http\Livewire\AssetIndex;
use Modules\Itam\Http\Livewire\MyEquipment;
use Modules\Itam\Http\Livewire\ShowAsset;
use Modules\Itam\Tests\Support\ItamScenario;

beforeEach(function (): void {
    $this->itam = ItamScenario::create();
    $this->actingAs($this->itam->manager);
});

it('lists the organization\'s assets with filters, and keeps employees out', function (): void {
    $this->itam->laptop(['asset_tag' => 'AST-1', 'serial_number' => 'ABC123']);
    $this->itam->laptop(['asset_tag' => 'AST-2', 'status' => 'retired']);
    app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Asset::factory()->create(['asset_tag' => 'THEIRS-1']));

    Livewire::test(AssetIndex::class)
        ->assertSee('AST-1')->assertSee('AST-2')->assertDontSee('THEIRS-1')
        ->set('search', 'abc123')->assertSee('AST-1')->assertDontSee('AST-2')
        ->set('search', '')->set('status', 'retired')->assertSee('AST-2')->assertDontSee('AST-1');

    $this->actingAs($this->itam->employee)->get(route('itam.assets.index'))->assertForbidden();
});

it('creates assets with the attributes of their category and unique serial numbers', function (): void {
    $this->itam->laptop(['serial_number' => 'SN-1']);

    Livewire::test(AssetForm::class)
        ->set('categoryId', $this->itam->laptops->id)
        ->set('serialNumber', 'sn-1')
        ->set('properties.memory_gb', 'lots')
        ->call('save')
        ->assertHasErrors(['serialNumber']);

    Livewire::test(AssetForm::class)
        ->set('categoryId', $this->itam->laptops->id)
        ->set('manufacturer', 'Lenovo')->set('model', 'T14')->set('serialNumber', 'SN-2')
        ->set('properties.memory_gb', 'lots')
        ->call('save')
        ->assertHasErrors(['properties.memory_gb'])
        ->set('properties.memory_gb', '32')
        ->set('purchaseCost', '1500')->set('currency', 'eur')->set('warrantyEndsOn', '2029-01-31')
        ->call('save')
        ->assertHasNoErrors();

    $asset = Asset::query()->where('serial_number', 'SN-2')->sole();
    expect($asset->displayName())->toBe('Lenovo T14')
        ->and($asset->properties)->toBe(['memory_gb' => 32])
        ->and($asset->currency)->toBe('EUR')
        ->and($asset->asset_tag)->toStartWith('AST-');
});

it('refuses a supplier of another organization', function (): void {
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Supplier::factory()->create());

    Livewire::test(AssetForm::class)
        ->set('categoryId', $this->itam->laptops->id)->set('supplierId', $theirs->id)
        ->call('save')
        ->assertHasErrors(['locationId']);

    expect(Asset::query()->exists())->toBeFalse();
});

it('lets agents hand out and take back equipment, but not change the register', function (): void {
    $laptop = $this->itam->laptop();
    $this->actingAs($this->itam->agent);

    Livewire::test(ShowAsset::class, ['asset' => $laptop])
        ->set('holderSearch', $this->itam->employee->email)
        ->assertSee($this->itam->employee->name)
        ->set('holderId', $this->itam->employee->id)
        ->call('assignAsset')
        ->assertHasNoErrors()
        ->call('changeStatus', 'lost')
        ->assertForbidden();

    expect($laptop->fresh()->assigned_to_id)->toBe($this->itam->employee->id);

    Livewire::test(ShowAsset::class, ['asset' => $laptop])->call('changeStatus', 'in_stock')->assertHasNoErrors();
    expect($laptop->fresh()->status)->toBe(AssetStatus::InStock);

    $this->get(route('itam.assets.edit', $laptop))->assertForbidden();
    Livewire::test(ShowAsset::class, ['asset' => $laptop])->call('delete')->assertForbidden();
});

it('does not hand an asset to someone outside the organization', function (): void {
    $laptop = $this->itam->laptop();
    $stranger = User::factory()->memberOf(Organization::factory()->create())->create();

    Livewire::test(ShowAsset::class, ['asset' => $laptop])
        ->set('holderSearch', $stranger->email)->assertDontSee($stranger->name)
        ->set('holderId', $stranger->id)->call('assignAsset')
        ->assertHasErrors(['holderId']);
});

it('disposes of a retired asset with proof of data wiping', function (): void {
    Storage::fake('attachments');
    $laptop = $this->itam->laptop(['status' => 'retired']);

    Livewire::test(ShowAsset::class, ['asset' => $laptop])
        ->set('files', [UploadedFile::fake()->createWithContent('wipe-certificate.pdf', "%PDF-1.4\n%test\n")])
        ->call('uploadDocuments')->assertHasNoErrors()
        ->set('disposalMethod', 'recycled')->set('dataWiped', '1')
        ->call('dispose')->assertHasNoErrors()
        ->assertSee('wipe-certificate.pdf');

    expect($laptop->fresh()->status)->toBe(AssetStatus::Disposed)->and($laptop->fresh()->data_wiped)->toBeTrue();
});

it('shows employees their own equipment only, without costs', function (): void {
    $mine = $this->itam->laptop(['asset_tag' => 'AST-MINE', 'purchase_cost' => '1234.00', 'currency' => 'EUR']);
    $theirs = $this->itam->laptop(['asset_tag' => 'AST-THEIRS']);
    app(AssignAsset::class)->handle($mine, $this->itam->employee, $this->itam->manager);
    app(AssignAsset::class)->handle($theirs, $this->itam->colleague, $this->itam->manager);

    $this->actingAs($this->itam->employee);
    Livewire::test(MyEquipment::class)->assertSee('AST-MINE')->assertDontSee('AST-THEIRS')->assertDontSee('1,234');
    $this->get(route('itam.assets.show', $mine))->assertForbidden();
    $this->get(route('home'))->assertSee('My equipment');

    $this->actingAs($this->itam->member(DefaultRole::Requester))->get(route('home'))->assertDontSee('My equipment');
});

it('links an asset to one configuration item, shown on the item page too', function (): void {
    $laptop = $this->itam->laptop(['asset_tag' => 'AST-SRV']);
    $type = CiType::factory()->create(['key' => 'server']);
    $first = ConfigurationItem::factory()->create(['type_id' => $type->id, 'name' => 'srv-app-01']);
    $second = ConfigurationItem::factory()->create(['type_id' => $type->id, 'name' => 'srv-app-02']);

    Livewire::test(ShowAsset::class, ['asset' => $laptop])
        ->set('itemSearch', 'srv-app')->assertSee('srv-app-01')
        ->call('linkItem', $first->id)
        ->call('linkItem', $second->id)
        ->assertSee('srv-app-02');

    expect(CiLink::query()->where('linkable_id', $laptop->id)->pluck('configuration_item_id')->all())->toBe([$second->id]);

    $this->actingAs($this->itam->agent);
    Livewire::test(ShowItem::class, ['item' => $second])->assertSee('AST-SRV');
});

it('lists the assets a contract covers on the contract page', function (): void {
    $contract = Contract::factory()->create(['title' => 'Dell ProSupport']);
    $this->itam->laptop(['asset_tag' => 'AST-COVERED', 'contract_id' => $contract->id]);

    Livewire::test(ShowContract::class, ['contract' => $contract])
        ->assertSee('Assets covered by this contract')
        ->assertSee('AST-COVERED');
});

it('lets asset managers manage categories in the panel, and not agents', function (): void {
    Filament::setCurrentPanel('admin');

    $this->get('/admin/itam/categories')->assertOk();

    Livewire::test(ManageCategories::class)
        ->callAction('create', data: ['name' => 'Projector', 'key' => 'projector', 'is_active' => true])
        ->assertHasNoFormErrors();
    expect(Category::query()->where('key', 'projector')->exists())->toBeTrue();

    $this->actingAs($this->itam->agent)->get('/admin/itam/categories')->assertForbidden();
});

it('gives a new organization the default categories once', function (): void {
    $fresh = Organization::factory()->create();
    $this->itam->manager->organizations()->attach($fresh);
    app(OrganizationContext::class)->set($fresh);
    app(OrganizationContext::class)->runAs($fresh, fn () => $this->itam->manager->assignRole('asset-manager'));

    Livewire::test(AssetIndex::class);
    expect(Category::query()->pluck('key')->sort()->values()->all())->toBe(['desktop', 'laptop', 'monitor', 'network_device', 'peripheral', 'phone', 'printer', 'server', 'storage', 'tablet']);

    Category::query()->where('key', 'tablet')->delete();
    Livewire::test(AssetIndex::class);
    expect(Category::query()->where('key', 'tablet')->exists())->toBeFalse();
});
