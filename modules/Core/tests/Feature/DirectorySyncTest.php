<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Application\Directory\DirectorySyncAborted;
use Modules\Core\Application\Directory\SyncDirectoryAccounts;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Directory\Directory;
use Modules\Core\Domain\Directory\DirectoryGroupMapping;
use Modules\Core\Domain\Directory\DirectoryUnavailable;
use Modules\Core\Domain\Directory\DirectoryUser;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Tests\Support\FakeDirectory;

const SYNC_TICKETING = 'cn=it-support,ou=groups,dc=corp,dc=example';
const SYNC_ADMINS = 'cn=it-admins,ou=groups,dc=corp,dc=example';

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();

    $this->directory = new FakeDirectory;
    app()->instance(Directory::class, $this->directory);

    $this->organization = Organization::factory()->create();
    DirectoryGroupMapping::factory()->for($this->organization)->forRole(DefaultRole::Agent)->create(['group_dn' => SYNC_TICKETING]);
    DirectoryGroupMapping::factory()->for($this->organization)->forRole(DefaultRole::TeamLead)->create(['group_dn' => SYNC_ADMINS]);
});

/**
 * A directory account already provisioned locally, with the agent role.
 */
function provisionedDirectoryUser(Organization $organization, string $username, bool $enabled = true, array $groups = [SYNC_TICKETING]): array
{
    $guid = (string) str()->uuid();
    $user = User::factory()->fromDirectory($guid)->memberOf($organization)->create(['email' => "{$username}@corp.example"]);
    app(OrganizationContext::class)->runAs($organization, fn () => $user->assignRole(DefaultRole::Agent->value));

    $entry = new DirectoryUser($guid, "CN={$username},OU=Staff,DC=corp,DC=example", $username, ucfirst($username), "{$username}@corp.example", $enabled);
    test()->directory->addUser($entry, 'secret', $groups);

    return [$user, $entry];
}

function rolesOf(User $user, Organization $organization): array
{
    return app(OrganizationContext::class)->runAs($organization, fn (): array => User::query()->findOrFail($user->id)->getRoleNames()->sort()->values()->all());
}

it('blocks accounts disabled in AD and lets them back in when re-enabled', function (): void {
    [$user, $entry] = provisionedDirectoryUser($this->organization, 'jdoe');
    $this->directory->addUser(new DirectoryUser($entry->guid, $entry->distinguishedName, 'jdoe', 'Jdoe', 'jdoe@corp.example', enabled: false), 'secret', [SYNC_TICKETING]);

    expect(app(SyncDirectoryAccounts::class)->handle()['disabled'])->toBe(1);

    $user->refresh();
    expect($user->directory_disabled_at)->not->toBeNull()
        ->and($user->is_active)->toBeTrue()
        ->and($user->canSignIn())->toBeFalse()
        // Roles are kept so re-enabling restores access as it was.
        ->and(rolesOf($user, $this->organization))->toBe(['agent']);

    $this->directory->addUser($entry, 'secret', [SYNC_TICKETING]);
    app(SyncDirectoryAccounts::class)->handle();

    expect($user->fresh()?->canSignIn())->toBeTrue();
});

it('blocks accounts removed from AD', function (): void {
    [$removed] = provisionedDirectoryUser($this->organization, 'gone');
    provisionedDirectoryUser($this->organization, 'stays');
    $this->directory->removeUser((string) $removed->directory_guid);

    expect(app(SyncDirectoryAccounts::class)->handle())->toMatchArray(['not_found' => 1, 'active' => 1])
        ->and($removed->fresh()?->canSignIn())->toBeFalse();
});

it('ends the open session of an account disabled by the sync', function (): void {
    [$user, $entry] = provisionedDirectoryUser($this->organization, 'jdoe');
    Route::middleware(['web', 'auth', 'organization'])->get('/__test/whoami', fn () => 'ok');

    $this->actingAs($user)->get('/__test/whoami')->assertOk();

    $this->directory->removeUser($entry->guid);
    app(SyncDirectoryAccounts::class)->handle();

    $this->actingAs($user->fresh())->get('/__test/whoami')->assertForbidden();
});

it('follows group changes and revokes access when no mapped group is left', function (): void {
    [$moved, $movedEntry] = provisionedDirectoryUser($this->organization, 'moved');
    [$left, $leftEntry] = provisionedDirectoryUser($this->organization, 'left');

    $this->directory->addUser($movedEntry, 'secret', [SYNC_ADMINS]);
    $this->directory->addUser($leftEntry, 'secret', ['cn=marketing,ou=groups,dc=corp,dc=example']);

    app(SyncDirectoryAccounts::class)->handle();

    expect(rolesOf($moved, $this->organization))->toBe(['team-lead'])
        ->and(rolesOf($left, $this->organization))->toBe([])
        ->and($left->fresh()?->isMemberOf($this->organization))->toBeFalse()
        ->and($left->fresh()?->current_organization_id)->toBeNull();
});

it('updates names and emails, but never onto another account\'s email', function (): void {
    [$user, $entry] = provisionedDirectoryUser($this->organization, 'jdoe');
    User::factory()->create(['email' => 'taken@corp.example']);

    $this->directory->addUser(new DirectoryUser($entry->guid, $entry->distinguishedName, 'jdoe', 'Jane Renamed', 'taken@corp.example', true), 'secret', [SYNC_TICKETING]);

    expect(app(SyncDirectoryAccounts::class)->handle()['email_conflict'])->toBe(1);
    expect($user->fresh())
        ->name->toBe('Jane Renamed')
        ->email->toBe('jdoe@corp.example');
});

it('changes nothing when AD is unreachable', function (): void {
    [$user] = provisionedDirectoryUser($this->organization, 'jdoe');
    $this->directory->makeUnavailable();

    expect(fn () => app(SyncDirectoryAccounts::class)->handle())->toThrow(DirectoryUnavailable::class);
    expect($user->fresh()?->canSignIn())->toBeTrue();
});

it('aborts when most accounts look missing, unless forced', function (): void {
    $users = [];
    foreach (range(1, 6) as $i) {
        [$users[]] = provisionedDirectoryUser($this->organization, "user{$i}");
    }
    // A wrong base DN looks like this: almost nobody is found.
    foreach (array_slice($users, 0, 4) as $user) {
        $this->directory->removeUser((string) $user->directory_guid);
    }

    expect(fn () => app(SyncDirectoryAccounts::class)->handle())->toThrow(DirectorySyncAborted::class);
    expect(User::query()->whereNotNull('directory_disabled_at')->count())->toBe(0);

    expect(app(SyncDirectoryAccounts::class)->handle(force: true)['not_found'])->toBe(4);
});

it('skips local accounts and accounts deactivated locally', function (): void {
    $local = User::factory()->memberOf($this->organization)->create();
    [$deactivated, $entry] = provisionedDirectoryUser($this->organization, 'off');
    $deactivated->forceFill(['is_active' => false])->save();
    $this->directory->removeUser($entry->guid);

    expect(array_sum(app(SyncDirectoryAccounts::class)->handle()))->toBe(0)
        ->and($local->fresh()?->directory_disabled_at)->toBeNull()
        ->and($deactivated->fresh()?->directory_disabled_at)->toBeNull();
});

it('clears the disabled mark when the user signs in again successfully', function (): void {
    [$user, $entry] = provisionedDirectoryUser($this->organization, 'jdoe');
    $user->forceFill(['directory_disabled_at' => now()])->save();

    Livewire\Livewire::test(Modules\Core\Http\Livewire\Auth\Login::class)
        ->set('login', 'jdoe')->set('password', 'secret')->call('submit')
        ->assertHasNoErrors();

    expect($user->fresh()?->directory_disabled_at)->toBeNull();
});

it('is scheduled only when AD sign-in is enabled', function (bool $enabled): void {
    config()->set('core.directory.enabled', $enabled);
    config()->set('core.directory.sync_schedule', '*/10 * * * *');
    app()->forgetInstance(Schedule::class);

    $events = collect(app(Schedule::class)->events())->filter(fn ($event): bool => str_contains((string) $event->command, 'core:directory:sync'));

    expect($events)->toHaveCount($enabled ? 1 : 0);
    if ($enabled) {
        expect($events->first()->expression)->toBe('*/10 * * * *');
    }
})->with(['enabled' => true, 'disabled' => false]);

it('reports a summary from the command', function (): void {
    provisionedDirectoryUser($this->organization, 'jdoe');

    $this->artisan('core:directory:sync')
        ->expectsOutputToContain('Active')
        ->assertSuccessful();
});
