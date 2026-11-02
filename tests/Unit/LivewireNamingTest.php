<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Livewire\Component;

/*
 * With the CSP build of Livewire, wire:click="name" and wire:submit="name" resolve
 * a property before a method: a property and an action sharing a name make the
 * action unreachable from the browser, while server-side tests still pass.
 */
it('never gives a Livewire property and an action the same name', function (): void {
    $clashes = [];

    foreach (glob(dirname(__DIR__, 2).'/modules/*/src/Http/Livewire/{,*/,*/*/}*.php', GLOB_BRACE) ?: [] as $file) {
        preg_match('/^namespace (.+);$/m', (string) file_get_contents($file), $namespace);
        $class = ($namespace[1] ?? '').'\\'.basename($file, '.php');

        if (! class_exists($class) || ! is_subclass_of($class, Component::class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        $properties = array_map(fn (ReflectionProperty $p): string => $p->getName(), $reflection->getProperties(ReflectionProperty::IS_PUBLIC));
        $methods = array_map(fn (ReflectionMethod $m): string => $m->getName(), array_filter($reflection->getMethods(ReflectionMethod::IS_PUBLIC), fn (ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === $class));

        foreach (array_intersect($properties, $methods) as $name) {
            $clashes[] = "{$class}::{$name}";
        }
    }

    expect($clashes)->toBe([]);
});

/*
 * $wire answers some bare names itself (wire:submit="upload" starts a file upload
 * in the browser instead of calling the action). The list is read from Livewire's
 * own script, so it follows Livewire updates.
 */
it('never names a Livewire property or action like a $wire helper', function (): void {
    $script = (string) file_get_contents(dirname(__DIR__, 2).'/vendor/livewire/livewire/dist/livewire.csp.js');
    preg_match('/var aliases = \{(.*?)\};/s', $script, $block);
    preg_match_all('/"(\w+)":\s*"\$/', $block[1] ?? '', $names);
    $reserved = $names[1];

    expect($reserved)->toContain('upload');

    $clashes = [];

    foreach (glob(dirname(__DIR__, 2).'/modules/*/src/Http/Livewire/{,*/,*/*/}*.php', GLOB_BRACE) ?: [] as $file) {
        preg_match('/^namespace (.+);$/m', (string) file_get_contents($file), $namespace);
        $class = ($namespace[1] ?? '').'\\'.basename($file, '.php');

        if (! class_exists($class) || ! is_subclass_of($class, Component::class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        $own = fn (ReflectionMethod|ReflectionProperty $member): bool => $member->getDeclaringClass()->getName() === $class;
        $names = [
            ...array_map(fn (ReflectionProperty $p): string => $p->getName(), array_filter($reflection->getProperties(ReflectionProperty::IS_PUBLIC), $own)),
            ...array_map(fn (ReflectionMethod $m): string => $m->getName(), array_filter($reflection->getMethods(ReflectionMethod::IS_PUBLIC), $own)),
        ];

        foreach (array_intersect($names, $reserved) as $name) {
            $clashes[] = "{$class}::{$name}";
        }
    }

    expect($clashes)->toBe([]);
});
