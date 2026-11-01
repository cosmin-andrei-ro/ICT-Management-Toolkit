<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Navigation;

use Closure;
use Modules\Core\Domain\Models\User;

/**
 * Main menu entries contributed by modules, so the Core layout needs no knowledge
 * of their routes. Visibility is decided per user; it hides links, it does not
 * replace authorization on the routes themselves.
 */
final class Navigation
{
    /** @var list<array{label: string, route: string, active: string, visible: Closure(User): bool}> */
    private array $items = [];

    /**
     * @param  string  $label  Translation key.
     * @param  string  $active  Route name pattern marking the entry as current (e.g. "ticketing.desk.*").
     * @param  Closure(User): bool  $visible
     */
    public function add(string $label, string $route, string $active, Closure $visible): void
    {
        $this->items[] = ['label' => $label, 'route' => $route, 'active' => $active, 'visible' => $visible];
    }

    /**
     * @return list<array{label: string, url: string, active: string}>
     */
    public function for(User $user): array
    {
        $visible = array_filter($this->items, fn (array $item): bool => ($item['visible'])($user));

        return array_values(array_map(fn (array $item): array => [
            'label' => __($item['label']),
            'url' => route($item['route']),
            'active' => $item['active'],
        ], $visible));
    }
}
