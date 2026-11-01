<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Email\MailboxReader;
use Modules\Ticketing\Domain\Models\InboundEmail;
use Modules\Ticketing\Domain\Models\Mailbox;
use Modules\Ticketing\Http\Filament\Resources\Mailboxes\Pages\ManageMailboxes;
use Modules\Ticketing\Tests\Support\FakeMailboxReader;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    config(['ticketing.email.allowed_ports' => [993, 143]]);
    $this->desk = TicketingScenario::create();
    $this->admin = $this->desk->member(DefaultRole::Administrator);
    app(OrganizationContext::class)->runAs($this->desk->organization, fn () => $this->desk->lead->givePermissionTo('core.admin.access'));
});

function mailboxData(array $overrides = []): array
{
    return [
        'name' => 'Service desk', 'address' => 'Helpdesk@Example.com', 'host' => 'mail.example.com', 'port' => 993,
        'encryption' => 'ssl', 'username' => 'helpdesk', 'password' => 'S3cret-imap-password', 'folder' => 'INBOX',
        'ticket_type' => 'incident', 'is_active' => true, ...$overrides,
    ];
}

it('keeps mailboxes and their log to administrators', function (): void {
    $this->actingAs($this->admin)->get('/admin/ticketing/mailboxes')->assertOk();
    $this->actingAs($this->admin)->get('/admin/ticketing/incoming-email')->assertOk();

    $this->actingAs($this->desk->lead)->get('/admin/ticketing/mailboxes')->assertForbidden();
    $this->actingAs($this->desk->lead)->get('/admin/ticketing/incoming-email')->assertForbidden();
});

it('stores the password encrypted, out of the audit trail and out of the form', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(ManageMailboxes::class)
        ->callAction('create', data: mailboxData())
        ->assertHasNoFormErrors();

    $mailbox = Mailbox::query()->sole();
    $stored = DB::table('ticketing_mailboxes')->where('id', $mailbox->id)->value('password');

    expect($mailbox->address)->toBe('helpdesk@example.com')
        ->and($mailbox->password)->toBe('S3cret-imap-password')
        ->and($stored)->not->toContain('S3cret')
        ->and($mailbox->credentials_updated_at)->not->toBeNull()
        ->and(AuditLog::query()->where('auditable_id', $mailbox->id)->get()->toJson())->not->toContain('S3cret')->not->toContain('password":');

    Livewire::test(ManageMailboxes::class)
        ->mountTableAction('edit', $mailbox)
        ->assertTableActionDataSet(['password' => null])
        ->setTableActionData(['name' => 'Renamed'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($mailbox->fresh()->password)->toBe('S3cret-imap-password')
        ->and($mailbox->fresh()->name)->toBe('Renamed');
});

it('refuses ports outside the allowed list and unencrypted connections', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(ManageMailboxes::class)
        ->callAction('create', data: mailboxData(['port' => 25, 'encryption' => 'none']))
        ->assertHasFormErrors(['port', 'encryption']);
});

it('tests the connection and reports only a reason', function (): void {
    $this->actingAs($this->admin);
    $mailbox = Mailbox::factory()->create();
    app()->instance(MailboxReader::class, new FakeMailboxReader(failure: 'authentication'));

    Livewire::test(ManageMailboxes::class)
        ->callTableAction('test', $mailbox)
        ->assertNotified(__('ticketing::email.admin.test_failed'));
});

it('lists the message log read-only', function (): void {
    $this->actingAs($this->admin);
    InboundEmail::factory()->create(['subject' => 'Printer offline']);

    $this->get('/admin/ticketing/incoming-email')->assertOk()->assertSee('Printer offline');
});
