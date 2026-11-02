<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Livewire\Contracts\ShowContract;
use Modules\Itam\Application\Actions\DisposeAsset;
use Modules\Itam\Application\Actions\ManageLicenseSeats;
use Modules\Itam\Application\Actions\RevealLicenseKey;
use Modules\Itam\Application\Exports\LicenseExport;
use Modules\Itam\Application\Licenses\LicenseCompliance;
use Modules\Itam\Application\Licenses\SendLicenseExpiryReminders;
use Modules\Itam\Application\Notifications\EndingSoon;
use Modules\Itam\Domain\Enums\DisposalMethod;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\LicenseAssignment;
use Modules\Itam\Domain\Models\SoftwareProduct;
use Modules\Itam\Http\Livewire\ImportAssets;
use Modules\Itam\Http\Livewire\LicenseForm;
use Modules\Itam\Http\Livewire\LicenseIndex;
use Modules\Itam\Http\Livewire\MyEquipment;
use Modules\Itam\Http\Livewire\ShowLicense;
use Modules\Itam\Tests\Support\ItamScenario;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    Notification::fake();
    $this->itam = ItamScenario::create();
    $this->actingAs($this->itam->manager);
});

function licenseFor(string $product, array $attributes = []): License
{
    $product = SoftwareProduct::query()->firstWhere('name', $product) ?? SoftwareProduct::factory()->create(['name' => $product]);

    return License::factory()->create(['product_id' => $product->id, ...$attributes]);
}

function currentCode(): string
{
    return (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');
}

it('creates a licence with a new product and keeps its key encrypted, out of the audit trail', function (): void {
    Livewire::test(LicenseForm::class)
        ->set('newProduct', 'Microsoft 365 E3')->set('name', '2026 agreement')
        ->set('quantity', '25')->set('newKey', 'ABCDE-12345-SECRET')
        ->set('expiresOn', '2026-12-31')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('newKey', '');

    $license = License::query()->sole();
    $raw = DB::table('itam_licenses')->where('id', $license->id)->value('license_key');

    expect($license->product->name)->toBe('Microsoft 365 E3')
        ->and($license->license_key)->toBe('ABCDE-12345-SECRET')
        ->and($raw)->not->toContain('SECRET')
        ->and($license->toJson())->not->toContain('SECRET')
        ->and(AuditLog::query()->get()->toJson())->not->toContain('SECRET')
        ->and(AuditLog::query()->where('auditable_id', $license->id)->whereJsonContains('new_values->license_key', 'changed')->exists())->toBeTrue();

    Livewire::test(LicenseForm::class, ['license' => $license])->set('quantity', '30')->call('save')->assertHasNoErrors();
    expect($license->fresh()->license_key)->toBe('ABCDE-12345-SECRET');

    Livewire::test(LicenseForm::class, ['license' => $license])->set('removeKey', true)->call('save');
    expect($license->fresh()->hasKey())->toBeFalse();
});

it('shows a key only after a valid code, records it, and throttles wrong codes', function (): void {
    $license = licenseFor('AutoCAD', ['license_key' => 'KEY-777']);

    Livewire::test(ShowLicense::class, ['license' => $license])
        ->set('revealCode', '000000')->call('revealKey')->assertHasErrors(['revealCode'])->assertDontSee('KEY-777')
        ->set('revealCode', currentCode())->call('revealKey')->assertHasNoErrors()->assertSee('KEY-777')
        ->call('hideKey')->assertDontSee('KEY-777');

    expect(AuditLog::query()->where('event', AuditEvent::SecretRevealed)->where('auditable_id', $license->id)->where('user_id', $this->itam->manager->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', AuditEvent::MfaFailed)->where('user_id', $this->itam->manager->id)->exists())->toBeTrue();

    $page = Livewire::test(ShowLicense::class, ['license' => $license]);
    foreach (range(1, 5) as $attempt) {
        $page->set('revealCode', '111111')->call('revealKey');
    }
    $page->set('revealCode', currentCode())->call('revealKey')->assertHasErrors(['revealCode' => __('itam::licenses.ui.reveal_throttled')])->assertDontSee('KEY-777');
    expect(app(RevealLicenseKey::class)->isThrottled($this->itam->manager))->toBeTrue();

    $this->actingAs($this->itam->agent);
    Livewire::test(ShowLicense::class, ['license' => $license])->set('revealCode', currentCode())->call('revealKey')->assertForbidden();
});

it('gives seats to people or devices by the licence model, and releases device seats on disposal', function (): void {
    $users = licenseFor('Adobe Acrobat', ['model' => 'per_user', 'quantity' => 1]);
    $devices = licenseFor('Windows 11 Pro', ['model' => 'per_device', 'quantity' => 5]);
    $laptop = $this->itam->laptop(['asset_tag' => 'AST-W11', 'status' => 'retired']);
    $seats = app(ManageLicenseSeats::class);

    $seats->giveToPerson($users, $this->itam->employee, $this->itam->manager);
    $seats->giveToPerson($users, $this->itam->colleague, $this->itam->agent);
    $seats->giveToDevice($devices, $laptop, $this->itam->manager);

    expect(fn () => $seats->giveToDevice($users, $laptop, $this->itam->manager))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $seats->giveToPerson($devices, $this->itam->employee, $this->itam->manager))->toThrow(InvalidArgumentException::class);

    $compliance = app(LicenseCompliance::class)->products(CarbonImmutable::today())->keyBy(fn ($product) => $product->product->name);
    expect($compliance['Adobe Acrobat']->used)->toBe(2)
        ->and($compliance['Adobe Acrobat']->isOverAllocated())->toBeTrue()
        ->and($compliance['Windows 11 Pro']->isOverAllocated())->toBeFalse();

    app(DisposeAsset::class)->handle($laptop, CarbonImmutable::today(), DisposalMethod::Recycled, true, null, $this->itam->manager);
    expect(LicenseAssignment::query()->where('license_id', $devices->id)->exists())->toBeFalse();

    Livewire::test(LicenseIndex::class)->set('over', true)->assertSee('Adobe Acrobat')->assertDontSee('Windows 11 Pro');
});

it('counts seats bought on valid licences only, and site licences without a limit', function (): void {
    $today = CarbonImmutable::today();
    $old = licenseFor('Visio', ['quantity' => 10, 'expires_on' => $today->subDay()]);
    licenseFor('Visio', ['quantity' => 2, 'expires_on' => $today->addYear()]);
    licenseFor('Antivirus', ['model' => 'site', 'quantity' => null]);
    app(ManageLicenseSeats::class)->giveToPerson($old, $this->itam->employee, $this->itam->manager);
    app(ManageLicenseSeats::class)->giveToPerson($old, $this->itam->colleague, $this->itam->manager);
    app(ManageLicenseSeats::class)->giveToPerson($old, $this->itam->agent, $this->itam->manager);

    $compliance = app(LicenseCompliance::class)->products($today)->keyBy(fn ($product) => $product->product->name);

    expect($compliance['Visio']->purchased)->toBe(2)
        ->and($compliance['Visio']->used)->toBe(3)
        ->and($compliance['Visio']->isOverAllocated())->toBeTrue()
        ->and($compliance['Visio']->licenses)->toHaveCount(1)
        ->and($compliance['Antivirus']->purchased)->toBeNull()
        ->and($compliance['Antivirus']->isOverAllocated())->toBeFalse();
});

it('does not switch a licence with seats between people and devices', function (): void {
    $license = licenseFor('Office');
    app(ManageLicenseSeats::class)->giveToPerson($license, $this->itam->employee, $this->itam->manager);

    Livewire::test(LicenseForm::class, ['license' => $license])->set('model', 'per_device')->call('save')->assertHasErrors(['model']);
    expect($license->fresh()->model->value)->toBe('per_user');
});

it('lets agents give seats but not see keys or change licences, and keeps employees out', function (): void {
    $license = licenseFor('Slack', ['license_key' => 'K-1']);
    $this->actingAs($this->itam->agent);

    Livewire::test(ShowLicense::class, ['license' => $license])
        ->set('seatSearch', $this->itam->employee->email)
        ->call('givePerson', $this->itam->employee->id)
        ->assertHasNoErrors()
        ->assertSee($this->itam->employee->name)
        ->assertDontSee('Show key');
    $this->get(route('itam.licenses.edit', $license))->assertForbidden();

    $this->actingAs($this->itam->employee)->get(route('itam.licenses.index'))->assertForbidden();
    Livewire::test(MyEquipment::class)->assertSee('My software')->assertSee('Slack')->assertDontSee('K-1');
});

it('keeps licences and seats inside their organization', function (): void {
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => License::factory()->create());
    $license = licenseFor('Zoom', ['model' => 'per_device']);
    $theirDevice = app(OrganizationContext::class)->runAs($theirs->organization_id, fn () => Asset::factory()->create());

    $this->get(route('itam.licenses.show', $theirs))->assertNotFound();
    Livewire::test(ShowLicense::class, ['license' => $license])->call('giveDevice', $theirDevice->id)->assertHasErrors(['seatSearch']);
});

it('imports licences without keys, creating products, all rows or none', function (): void {
    licenseFor('Jira', ['name' => 'Cloud Standard', 'quantity' => 10]);
    $csv = "product;publisher;name;model;quantity;expires_on;license_key\n"
        ."Jira;Atlassian;Cloud Standard;;50;2027-01-31;LEAKED\n"
        ."Figma;Figma;Team;per user;5;;\n"
        ."CrowdStrike;;EDR;site;;;\n";

    Livewire::test(ImportAssets::class, ['kind' => 'licenses'])
        ->set('kind', 'licenses')
        ->set('file', UploadedFile::fake()->createWithContent('licenses.csv', $csv))
        ->call('analyse')
        ->call('import')
        ->assertSet('imported', 3);

    $jira = License::query()->whereHas('product', fn ($query) => $query->where('name', 'Jira'))->sole();
    expect($jira->quantity)->toBe(50)
        ->and($jira->product->publisher)->toBe('Atlassian')
        ->and($jira->hasKey())->toBeFalse()
        ->and(SoftwareProduct::query()->where('name', 'Figma')->exists())->toBeTrue();

    $bad = "product;name;model;quantity\nVisio;;per_user;0\n";
    Livewire::test(ImportAssets::class)->set('kind', 'licenses')
        ->set('file', UploadedFile::fake()->createWithContent('bad.csv', $bad))
        ->call('analyse')->call('import')->assertHasErrors(['file']);
    expect(SoftwareProduct::query()->where('name', 'Visio')->exists())->toBeFalse();

    $this->actingAs($this->itam->agent)->get(route('itam.assets.import', ['kind' => 'licenses']))->assertForbidden();
});

it('exports licences with seats in use, never the key', function (): void {
    $license = licenseFor('Notion', ['license_key' => 'NEVER-EXPORTED']);
    app(ManageLicenseSeats::class)->giveToPerson($license, $this->itam->employee, $this->itam->manager);

    [$sheet] = app(LicenseExport::class)->sheets(Export::factory()->create(['type' => LicenseExport::TYPE, 'user_id' => $this->itam->manager->id]));
    $row = array_combine($sheet->headings, iterator_to_array($sheet->rows, false)[0]);

    expect($row)->toMatchArray(['product' => 'Notion', 'seats_used' => 1])
        ->and(json_encode([$sheet->headings, $row]))->not->toContain('NEVER-EXPORTED');
});

it('lists licences under a contract, and reminds once before expiry', function (): void {
    $contract = Contract::factory()->create();
    licenseFor('Veeam', ['name' => 'Backup', 'contract_id' => $contract->id, 'expires_on' => CarbonImmutable::today()->addDays(20)]);

    Livewire::test(ShowContract::class, ['contract' => $contract])->assertSee('Licences under this contract')->assertSee('Veeam · Backup');

    expect(app(SendLicenseExpiryReminders::class)->handle())->toBe(1)
        ->and(app(SendLicenseExpiryReminders::class)->handle())->toBe(0);
    Notification::assertSentTo($this->itam->manager, EndingSoon::class, fn (EndingSoon $mail): bool => $mail->kind === 'license');
    Notification::assertNotSentTo($this->itam->agent, EndingSoon::class);
});
