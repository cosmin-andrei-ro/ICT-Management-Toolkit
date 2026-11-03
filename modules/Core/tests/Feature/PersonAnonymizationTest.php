<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Application\PersonalData\AnonymizePerson;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Reporting\Domain\Models\ReportSubscription;
use Modules\Ticketing\Domain\Models\InboundEmail;
use Modules\Ticketing\Domain\Models\Ticket;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->admin = User::factory()->memberOf($this->organization)->withTwoFactor()->create(['name' => 'Admin Person']);
    $this->admin->assignRole(DefaultRole::Administrator->value);
    $this->person = User::factory()->memberOf($this->organization)->withTwoFactor()->create(['name' => 'Ana Popescu', 'email' => 'ana.popescu@example.com']);
    $this->person->assignRole(DefaultRole::Agent->value);
});

function freshCode(int $offset = 0): string
{
    return (new Google2FA)->oathTotp('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', (int) floor(time() / 30) + $offset);
}

function anonymize(User $person, User $admin, ?string $confirmation = null, ?string $code = null): int
{
    return app(AnonymizePerson::class)->handle($person, $confirmation ?? $person->email, $code ?? freshCode(), $admin);
}

it('removes the name, the address and every way to sign in, and keeps the records the person took part in', function (): void {
    $ticket = Ticket::factory()->create(['requester_id' => $this->person->id]);
    DB::table('sessions')->insert(['id' => 'session-1', 'user_id' => $this->person->id, 'payload' => '', 'last_activity' => time()]);
    $this->person->createToken('integration');

    anonymize($this->person, $this->admin);

    $person = User::query()->findOrFail($this->person->id);
    expect($person->name)->toStartWith('Former user #')
        ->and($person->email)->toEndWith('@anonymized.invalid')
        ->and($person->password)->toBeNull()
        ->and($person->is_active)->toBeFalse()
        ->and($person->two_factor_secret)->toBeNull()
        ->and($person->anonymized_at)->not->toBeNull()
        ->and($person->organizations()->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $person->id)->exists())->toBeFalse()
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $person->id)->exists())->toBeFalse()
        ->and($ticket->fresh()->requester_id)->toBe($person->id)
        ->and(DB::table('core_model_has_roles')->where('model_id', $person->id)->exists())->toBeFalse();
});

it('takes the name, the address and the IP addresses out of the audit log, and keeps who did what', function (): void {
    app(AuditTrail::class)->record(AuditEvent::LoginFailed, null, null, ['email' => 'ana.popescu@example.com']);
    (new AuditLog)->forceFill(['organization_id' => null, 'user_id' => $this->person->id, 'event' => AuditEvent::Login, 'auditable_type' => 'core.user', 'auditable_id' => $this->person->id, 'ip_address' => '203.0.113.7', 'user_agent' => 'Firefox'])->save();
    app(AuditTrail::class)->record(AuditEvent::Updated, $this->admin, ['name' => 'Ana Popescu Jr'], ['name' => 'Admin Person'], $this->admin->id);

    $redacted = anonymize($this->person, $this->admin);

    $log = AuditLog::query()->get();
    $text = $log->map(fn (AuditLog $entry): string => json_encode([$entry->old_values, $entry->new_values, $entry->ip_address, $entry->user_agent]))->implode(' ');

    expect($redacted)->toBeGreaterThan(1)
        ->and($text)->not->toContain('ana.popescu@example.com')->not->toContain('"Ana Popescu"')->not->toContain('203.0.113.7')->not->toContain('Firefox')
        // A longer name that only starts the same is someone else's.
        ->and($text)->toContain('Ana Popescu Jr')
        ->and($log->where('event', AuditEvent::Login)->first()?->user_id)->toBe($this->person->id);

    $done = AuditLog::query()->where('event', AuditEvent::PersonAnonymized)->sole();
    expect($done->user_id)->toBe($this->admin->id)->and(json_encode($done->new_values))->not->toContain('Ana');
});

it('keeps the audit log append-only outside the redaction', function (): void {
    $entry = AuditLog::query()->firstOrFail();

    expect(fn () => DB::table('core_audit_logs')->where('id', $entry->id)->update(['new_values' => null]))->toThrow(QueryException::class);
    expect(fn () => DB::table('core_audit_logs')->where('id', $entry->id)->delete())->toThrow(QueryException::class);
});

it('erases what only matters to the person in the modules', function (): void {
    $email = InboundEmail::factory()->create(['from_address' => 'Ana.Popescu@example.com', 'subject' => 'My laptop']);
    (new ReportSubscription)->forceFill(['user_id' => $this->person->id])->save();

    anonymize($this->person, $this->admin);

    expect($email->fresh())->from_address->toBe('anonymized')->subject->toBeNull()
        ->and(ReportSubscription::query()->where('user_id', $this->person->id)->exists())->toBeFalse();
});

it('asks for the e-mail typed out and a fresh code, and refuses where the account is not this organization\'s alone', function (): void {
    expect(fn () => anonymize($this->person, $this->admin, confirmation: 'someone@example.com'))->toThrow(ValidationException::class)
        ->and(fn () => anonymize($this->person, $this->admin, code: '000000'))->toThrow(ValidationException::class)
        ->and(AuditLog::query()->where('event', AuditEvent::MfaFailed)->where('user_id', $this->admin->id)->exists())->toBeTrue()
        ->and(fn () => anonymize($this->admin, $this->admin))->toThrow(ValidationException::class);

    $this->person->organizations()->attach(Organization::factory()->create());
    expect(fn () => anonymize($this->person, $this->admin, code: freshCode(1)))->toThrow(ValidationException::class)
        ->and($this->person->fresh()->anonymized_at)->toBeNull();
});

it('never removes the last administrator', function (): void {
    $other = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
    $other->assignRole(DefaultRole::Administrator->value);
    $this->admin->syncRoles([]);
    $other->forceFill(['two_factor_last_used_step' => null])->save();

    // $this->admin is no longer one; $other is the last.
    expect(fn () => anonymize($other, $this->person->fresh()))->toThrow(ValidationException::class);
});

it('is offered to administrators on the personal data page', function (): void {
    Filament::setCurrentPanel('admin');

    $this->actingAs($this->admin)->get('/admin/personal-data')->assertOk()->assertSee('Anonymize a person');
});
