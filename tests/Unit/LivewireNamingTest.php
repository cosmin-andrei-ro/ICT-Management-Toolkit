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
