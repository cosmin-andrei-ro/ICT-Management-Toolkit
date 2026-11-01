<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\ModulesServiceProvider;
use Tests\Fixtures\DummyModule\DummyServiceProvider;

function registerModules(array $modules): void
{
    config()->set('modules.modules', $modules);

    (new ModulesServiceProvider(app()))->register();
}

it('boots an enabled module with its routes, views and translations', function (): void {
    registerModules(['dummy' => ['provider' => DummyServiceProvider::class, 'enabled' => true]]);

    expect(app()->providerIsLoaded(DummyServiceProvider::class))->toBeTrue();

    $this->get('/__dummy-module')
        ->assertOk()
        ->assertSeeText('Hello from the dummy module');

    $this->getJson('/api/v1/dummy')
        ->assertOk()
        ->assertExactJson(['module' => 'dummy']);
});

it('skips a disabled module', function (): void {
    registerModules(['dummy' => ['provider' => DummyServiceProvider::class, 'enabled' => false]]);

    expect(app()->providerIsLoaded(DummyServiceProvider::class))->toBeFalse();

    $this->get('/__dummy-module')->assertNotFound();
});

it('rejects a provider that does not extend the module base class', function (): void {
    registerModules(['broken' => ['provider' => AppServiceProvider::class, 'enabled' => true]]);
})->throws(LogicException::class, 'Module [broken] provider must extend');
