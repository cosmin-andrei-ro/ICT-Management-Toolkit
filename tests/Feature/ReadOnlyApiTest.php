<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Documentation\Domain\Models\Page;
use Modules\Projects\Domain\Models\Project;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    app(OrganizationContext::class)->set(Organization::query()->where('slug', 'demo')->sole());
});

/**
 * @param  list<string>  $abilities
 * @return array<string, string>
 */
function readOnlyHeaders(string $email, array $abilities): array
{
    $token = app(IssueApiToken::class)->handle(User::query()->where('email', $email)->sole(), 'read-only', $abilities);
    auth()->forgetGuards();

    return ['Authorization' => 'Bearer '.$token->plainTextToken, 'Accept' => 'application/json'];
}

it('reads the directory, suppliers, data centre, documentation, projects and indicators of the demo company', function (): void {
    $admin = readOnlyHeaders('admin@example.com', ['directory:read', 'suppliers:read', 'dcim:read', 'documentation:read', 'projects:read', 'metrics:read']);

    $this->getJson('/api/v1/directory/people?filter[search]=agent@', $admin)->assertOk()->assertJsonPath('data.0.name', 'Andrei Pop')->assertJsonMissingPath('data.0.password');
    $this->getJson('/api/v1/directory/teams', $admin)->assertOk()->assertJsonFragment(['name' => 'IT Support']);
    $this->getJson('/api/v1/directory/departments', $admin)->assertOk();
    $this->getJson('/api/v1/directory/locations?filter[kind]=room', $admin)->assertOk()->assertJsonCount(2, 'data');

    $supplier = $this->getJson('/api/v1/suppliers?filter[search]=Hardware', $admin)->assertOk()->json('data.0.id');
    $this->getJson("/api/v1/suppliers/{$supplier}", $admin)->assertOk()->assertJsonPath('data.name', 'Demo Hardware');
    $this->getJson('/api/v1/contracts?sort=end_date', $admin)->assertOk()->assertJsonStructure(['data' => [['id', 'supplier', 'end_date', 'value']], 'meta']);

    $rack = Rack::query()->where('name', 'BUC-R01')->sole();
    $this->getJson('/api/v1/dcim/racks', $admin)->assertOk()->assertJsonCount(6, 'data');
    $this->getJson("/api/v1/dcim/racks/{$rack->id}", $admin)->assertOk()->assertJsonCount(4, 'data.equipment')->assertJsonFragment(['name' => 'hv-01']);
    $this->getJson('/api/v1/dcim/power?filter[kind]=ups', $admin)->assertOk()->assertJsonCount(3, 'data');
    $this->getJson('/api/v1/dcim/subnets', $admin)->assertOk()->assertJsonCount(4, 'data');
    $this->getJson('/api/v1/dcim/addresses?filter[search]=10.20.0', $admin)->assertOk();

    $this->getJson('/api/v1/documentation/spaces', $admin)->assertOk()->assertJsonFragment(['name' => 'Help for employees']);
    $vpn = Page::query()->where('title', 'Connect to the VPN from home')->sole();
    $this->getJson("/api/v1/documentation/pages/{$vpn->id}", $admin)->assertOk()->assertJsonPath('data.title', 'Connect to the VPN from home');

    $over = Project::query()->where('name', 'Core switch replacement')->sole();
    $this->getJson('/api/v1/projects?filter[status]=active', $admin)->assertOk()->assertJsonCount(3, 'data');
    $this->getJson("/api/v1/projects/{$over->id}", $admin)->assertOk()->assertJsonPath('data.budget.over', true)->assertJsonPath('data.latest_report.overall', 'red');

    $this->getJson('/api/v1/metrics', $admin)->assertOk()->assertJsonFragment(['key' => 'dcim.single_fed', 'value' => 1]);
    $this->getJson('/api/v1/metrics/dcim.single_fed/history?days=30', $admin)->assertOk()->assertJsonCount(30, 'data');
});

it('shows each person only what they may see', function (): void {
    // An employee reads what is open to her, never staff spaces or drafts.
    $employee = readOnlyHeaders('employee@example.com', ['documentation:read', 'projects:read', 'metrics:read', 'dcim:read']);
    // As on screen: the help space, and the application space because she owns the mail application - never staff spaces.
    expect($this->getJson('/api/v1/documentation/spaces', $employee)->assertOk()->json('data.*.name'))->toBe(['Applications', 'Help for employees']);
    $pending = Page::query()->where('title', 'Access control policy')->sole();
    $this->getJson("/api/v1/documentation/pages/{$pending->id}", $employee)->assertForbidden();
    $this->getJson('/api/v1/dcim/racks', $employee)->assertForbidden();
    $this->getJson('/api/v1/metrics', $employee)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/metrics/dcim.single_fed/history', $employee)->assertNotFound();

    // A sponsor without portfolio access sees only their project.
    $this->getJson('/api/v1/projects', readOnlyHeaders('finance@example.com', ['projects:read']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Second storage array');

    // An ability does not open what the role does not.
    $this->getJson('/api/v1/suppliers', readOnlyHeaders('employee@example.com', ['suppliers:read']))->assertForbidden();
    $this->getJson('/api/v1/suppliers', readOnlyHeaders('admin@example.com', ['dcim:read']))->assertForbidden();
});
