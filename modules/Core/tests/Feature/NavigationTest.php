<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Core\Tests\Support\ContractScenario;

beforeEach(function (): void {
    $this->scenario = ContractScenario::create();
});

/**
 * @return list<string|array<string, list<string>>> Labels, with groups as [group => [labels]].
 */
function menuOf(User $user): array
{
    return array_map(
        fn (array $entry): string|array => isset($entry['items']) ? [$entry['label'] => array_column($entry['items'], 'label')] : $entry['label'],
        app(Navigation::class)->for($user),
    );
}

it('groups the menu of IT staff, in a fixed order whatever the module order', function (): void {
    $lead = $this->scenario->member(DefaultRole::TeamLead);

    expect(menuOf($lead))->toBe([
        'Request a service',
        'My tickets',
        ['Service desk' => ['Service desk', 'Known errors', 'Change calendar', 'Approvals', 'Reports']],
        ['Configuration & assets' => ['CMDB', 'Assets', 'Licences']],
        ['Risk & continuity' => ['Business impact analysis', 'Recovery gaps', 'Continuity plans']],
        ['Suppliers & contracts' => ['Suppliers', 'Contracts']],
        'Administration',
    ]);
});

it('shows a group with a single entry as a plain link', function (): void {
    expect(menuOf($this->scenario->agent))->toContain('Suppliers')
        ->and(menuOf($this->scenario->requester))->toBe(['Request a service', 'My tickets']);
});

it('marks the group of the current page and the page itself', function (): void {
    $lead = $this->scenario->member(DefaultRole::TeamLead);

    $this->actingAs($lead)->get(route('core.contracts.index'))
        ->assertOk()
        ->assertSeeInOrder(['data-nav-menu', 'Suppliers &amp; contracts', 'aria-current="page"', 'Contracts'], false);
});

it('refuses an unknown group', function (): void {
    app(Navigation::class)->add('x', 'home', 'home', fn (): bool => true, 'nope');
})->throws(InvalidArgumentException::class);
