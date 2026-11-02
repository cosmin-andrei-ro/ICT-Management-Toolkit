<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Supplier;
use Modules\Itam\Application\Actions\AssignAsset;
use Modules\Itam\Application\Exports\AssetExport;
use Modules\Itam\Application\Notifications\AssetHandedOver;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Http\Livewire\AssetIndex;
use Modules\Itam\Http\Livewire\ImportAssets;
use Modules\Itam\Tests\Support\ItamScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->itam = ItamScenario::create();
    $this->actingAs($this->itam->manager);
});

function assetCsv(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('assets.csv', $content);
}

it('creates assets, hands them to their holders without emailing them, and updates by tag or serial number', function (): void {
    $site = Location::factory()->create(['name' => 'HQ']);
    Location::factory()->create(['name' => 'Store room', 'kind' => 'room', 'parent_id' => $site->id]);
    Supplier::factory()->create(['name' => 'Acme']);
    $existing = $this->itam->laptop(['asset_tag' => 'AST-OLD', 'serial_number' => 'SN-OLD', 'model' => 'Old model']);
    $bySerial = $this->itam->laptop(['asset_tag' => 'AST-S', 'serial_number' => 'SN-BY-SERIAL']);

    $csv = "category;asset_tag;serial_number;manufacturer;model;holder;location;supplier;purchase_date;purchase_cost;currency;memory_gb\n"
        ."laptop;;SN-NEW-1;Dell;Latitude 5440;{$this->itam->employee->email};HQ > Store room;acme;2026-01-15;1200,50;EUR;16\n"
        ."Laptop;LBL-77;;HP;EliteBook;;Store room;;;;;\n"
        .";ast-old;;;New model;;;;;;;32\n"
        .";;sn-by-serial;Lenovo;;;;;;;;\n";

    $page = Livewire::test(ImportAssets::class)->set('file', assetCsv($csv))->call('analyse')->assertHasNoErrors();
    expect(array_column($page->get('preview'), 'action'))->toBe(['create', 'create', 'update', 'update'])
        ->and(Asset::query()->count())->toBe(2);

    $page->call('import')->assertSet('imported', 4);

    $new = Asset::query()->where('serial_number', 'SN-NEW-1')->sole();
    expect($new->status)->toBe(AssetStatus::Deployed)
        ->and($new->assigned_to_id)->toBe($this->itam->employee->id)
        ->and($new->purchase_cost)->toBe('1200.50')
        ->and($new->properties)->toBe(['memory_gb' => 16])
        ->and($new->asset_tag)->toStartWith('AST-')
        ->and(Asset::query()->where('asset_tag', 'LBL-77')->sole()->location?->name)->toBe('Store room')
        ->and($existing->fresh()->model)->toBe('New model')
        ->and($existing->fresh()->serial_number)->toBe('SN-OLD')
        ->and($bySerial->fresh()->manufacturer)->toBe('Lenovo');
    Notification::assertNothingSent();
});

it('imports nothing when any row is wrong, and says what is wrong', function (): void {
    $this->itam->laptop(['asset_tag' => 'AST-1', 'serial_number' => 'SN-TAKEN', 'status' => 'retired']);
    $csv = "category;asset_tag;serial_number;status;holder;purchase_date;purchase_cost;currency;location\n"
        ."laptop;A-1;;;;;;;\n"
        ."tablet;A-2;;;;;;;\n"
        ."laptop;A-1;;;;;;;\n"
        ."laptop;A-3;sn-taken;;;;;;\n"
        ."laptop;AST-1;;in stock;;;;;\n"
        ."laptop;AST-1;;;{$this->itam->employee->email};;;;\n"
        ."laptop;A-4;;broken;nobody@example.com;31.01.2026;12;;Mars\n";

    $page = Livewire::test(ImportAssets::class)->set('file', assetCsv($csv))->call('analyse');
    $errors = array_column($page->get('preview'), 'errors', 'line');

    expect($errors[2])->toBe([])
        ->and(implode(' ', $errors[3]))->toContain('category')
        ->and(implode(' ', $errors[4]))->toContain('line 2')
        ->and(implode(' ', $errors[5]))->toContain('AST-1')
        ->and(implode(' ', $errors[6]))->toContain('status')
        ->and(implode(' ', $errors[7]))->toContain('cannot be handed out')
        ->and(count($errors[8]))->toBe(5);

    $page->call('import')->assertHasErrors(['file']);
    expect(Asset::query()->count())->toBe(1);
});

it('keeps imports to the people who keep the register', function (): void {
    $this->actingAs($this->itam->agent)->get(route('itam.assets.import'))->assertForbidden();
});

it('exports the register with the import columns, so it can be imported back', function (): void {
    $laptop = $this->itam->laptop(['asset_tag' => 'AST-9', 'serial_number' => 'SN-9', 'manufacturer' => 'Dell', 'model' => 'XPS', 'properties' => ['memory_gb' => 32]]);
    app(AssignAsset::class)->handle($laptop, $this->itam->employee, $this->itam->manager);
    $export = Export::factory()->create(['type' => AssetExport::TYPE, 'user_id' => $this->itam->manager->id]);

    [$sheet] = app(AssetExport::class)->sheets($export);
    $rows = iterator_to_array($sheet->rows, false);
    $row = array_combine($sheet->headings, $rows[0]);

    expect(array_slice($sheet->headings, 0, 3))->toBe(['category', 'asset_tag', 'serial_number'])
        ->and($row)->toMatchArray(['category' => 'laptop', 'asset_tag' => 'AST-9', 'status' => 'deployed', 'holder' => $this->itam->employee->email, 'memory_gb' => 32]);

    expect(fn () => app(RequestExport::class)->handle($this->itam->agent, AssetExport::TYPE, ExportFormat::Xlsx, []))->toThrow(AuthorizationException::class);

    Livewire::test(AssetIndex::class)->call('export', 'xlsx');
    expect(Export::query()->where('type', AssetExport::TYPE)->where('user_id', $this->itam->manager->id)->count())->toBe(2);
});

it('emails the holder when an asset is handed out from its page', function (): void {
    app(AssignAsset::class)->handle($this->itam->laptop(['asset_tag' => 'AST-MAIL']), $this->itam->employee, $this->itam->manager);

    Notification::assertSentTo($this->itam->employee, AssetHandedOver::class, fn (AssetHandedOver $mail): bool => $mail->assetTag === 'AST-MAIL');
});
