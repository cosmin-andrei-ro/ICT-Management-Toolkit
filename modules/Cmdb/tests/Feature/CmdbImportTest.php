<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Domain\Models\Relationship;
use Modules\Cmdb\Http\Livewire\ImportPage;
use Modules\Cmdb\Tests\Support\CmdbScenario;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function (): void {
    $this->cmdb = CmdbScenario::create();
    $this->server = CiType::factory()->create(['key' => 'server', 'name' => 'Server', 'attribute_schema' => [
        ['key' => 'operating_system', 'label' => 'Operating system', 'type' => 'text', 'required' => true],
        ['key' => 'memory_gb', 'label' => 'Memory (GB)', 'type' => 'number'],
    ]]);
    $this->actingAs($this->cmdb->agent);
});

function csvUpload(string $content, string $name = 'items.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

it('previews without saving, then imports every row', function (): void {
    $csv = "type;name;identifier;criticality;owner;operating_system;memory_gb\n"
        ."server;srv-db-01;SRV-DB-01;Critical;{$this->cmdb->lead->email};Debian 13;64\n"
        ."Server;srv-app-01;SRV-APP-01;;;Windows Server 2025;\n";

    $page = Livewire::test(ImportPage::class)
        ->set('file', csvUpload($csv))
        ->call('analyse')
        ->assertHasNoErrors()
        ->assertSee('srv-db-01');

    expect($page->get('preview'))->toHaveCount(2)
        ->and(ConfigurationItem::query()->count())->toBe(0);

    $page->call('import')->assertSet('imported', 2);

    $db = ConfigurationItem::query()->where('identifier', 'SRV-DB-01')->sole();
    expect($db->criticality->value)->toBe('critical')
        ->and($db->owner_id)->toBe($this->cmdb->lead->id)
        ->and($db->properties)->toEqual(['operating_system' => 'Debian 13', 'memory_gb' => 64]);
});

it('updates items by identifier and keeps values for empty cells', function (): void {
    $existing = ConfigurationItem::factory()->create(['type_id' => $this->server->id, 'name' => 'old', 'identifier' => 'SRV-01', 'properties' => ['operating_system' => 'Debian 12', 'memory_gb' => 16]]);

    Livewire::test(ImportPage::class)
        ->set('file', csvUpload("identifier,name,memory_gb\nsrv-01,srv-01,32\n"))
        ->call('analyse')
        ->assertSee(__('cmdb::import.actions.update'))
        ->call('import');

    expect($existing->fresh()->name)->toBe('srv-01')
        ->and($existing->fresh()->properties)->toEqual(['operating_system' => 'Debian 12', 'memory_gb' => 32]);
});

it('imports nothing while any row has an error, and says why', function (): void {
    $csv = "type;name;identifier;status;operating_system\n"
        ."server;good;GOOD-1;;Debian\n"
        ."printer;bad-type;BAD-1;;x\n"
        ."server;no-os;BAD-2;;\n"
        ."server;bad-status;BAD-3;broken;x\n"
        ."server;twice;GOOD-1;;x\n";

    $page = Livewire::test(ImportPage::class)->set('file', csvUpload($csv))->call('analyse');

    $errors = collect($page->get('preview'))->where('action', 'error');
    expect($errors->pluck('line')->all())->toBe([3, 4, 5, 6])
        ->and($errors->firstWhere('line', 5)['errors'][0])->toContain('broken');

    $page->call('import')->assertHasErrors(['file']);
    expect(ConfigurationItem::query()->count())->toBe(0);
});

it('reads Excel files', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Type', 'Name', 'Identifier', 'Operating_system']));
    $writer->addRow(Row::fromValues(['server', 'srv-xl-01', 'SRV-XL-01', 'Rocky Linux 10']));
    $writer->close();

    Livewire::test(ImportPage::class)
        ->set('file', UploadedFile::fake()->createWithContent('items.xlsx', (string) file_get_contents($path)))
        ->call('analyse')->assertHasNoErrors()
        ->call('import')->assertSet('imported', 1);

    expect(ConfigurationItem::query()->where('identifier', 'SRV-XL-01')->sole()->properties)->toBe(['operating_system' => 'Rocky Linux 10']);
});

it('imports relationships between existing items, once', function (): void {
    $app = $this->cmdb->item('payroll-app', ['identifier' => 'APP-PAY']);
    $vm = $this->cmdb->item('vm-07', ['identifier' => 'VM-07']);
    $csv = "source;relationship;target\nAPP-PAY;runs on;VM-07\napp-pay;runs_on;vm-07\nAPP-PAY;hosts;NOPE\n";

    Livewire::test(ImportPage::class)->set('kind', 'relationships')->set('file', csvUpload($csv))->call('analyse')
        ->assertSee(__('cmdb::import.actions.error'));

    Livewire::test(ImportPage::class)->set('kind', 'relationships')->set('file', csvUpload("source;relationship;target\nAPP-PAY;runs on;VM-07\n"))->call('analyse')->call('import')->assertSet('imported', 1);
    Livewire::test(ImportPage::class)->set('kind', 'relationships')->set('file', csvUpload("source;relationship;target\nAPP-PAY;runs on;VM-07\n"))->call('analyse')->assertSee(__('cmdb::import.actions.unchanged'))->call('import')->assertSet('imported', 0);

    expect(Relationship::query()->sole()->type)->toBe(RelationshipType::RunsOn)
        ->and(Relationship::query()->sole()->source_id)->toBe($app->id)
        ->and(Relationship::query()->sole()->target_id)->toBe($vm->id);
});

it('refuses other formats, files without a header, and too many rows', function (): void {
    Livewire::test(ImportPage::class)->set('file', UploadedFile::fake()->image('photo.png'))->call('analyse')->assertHasErrors(['file']);

    $tooMany = "type,name\n".str_repeat("server,x\n", ImportPage::MAX_ROWS + 1);
    Livewire::test(ImportPage::class)->set('file', csvUpload($tooMany))->call('analyse')->assertHasErrors(['file']);
});

it('is for people who manage the CMDB', function (): void {
    $this->actingAs($this->cmdb->requester)->get(route('cmdb.import'))->assertForbidden();
    $this->actingAs($this->cmdb->lead)->get(route('cmdb.import'))->assertOk();
});
