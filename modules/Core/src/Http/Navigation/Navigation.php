<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Navigation;

use Closure;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;

/**
 * Main menu entries contributed by modules, so the Core layout needs no knowledge
 * of their routes. Entries are links or belong to a group shown as a drop-down;
 * groups let several modules share one heading (the CMDB and asset management
 * under "Configuration & assets"). Visibility is decided per user; it hides
 * links, it does not replace authorization on the routes themselves.
 */
final class Navigation
{
    /** Shared groups, declared here so modules can rely on them. */
    public const string SERVICE_DESK = 'service_desk';

    public const string CONFIGURATION = 'configuration';

    public const string SUPPLIERS = 'suppliers';

    public const string RESILIENCE = 'resilience';

    public const string COMPLIANCE = 'compliance';

    /** @var array<string, array{label: string, order: int}> */
    private array $groups = [
        self::SERVICE_DESK => ['label' => 'core::layout.groups.service_desk', 'order' => 30],
        self::CONFIGURATION => ['label' => 'core::layout.groups.configuration', 'order' => 40],
        self::RESILIENCE => ['label' => 'core::layout.groups.resilience', 'order' => 45],
        self::COMPLIANCE => ['label' => 'core::layout.groups.compliance', 'order' => 47],
        self::SUPPLIERS => ['label' => 'core::layout.groups.suppliers', 'order' => 50],
    ];

    /** @var list<array{label: string, route: string, active: string, visible: Closure(User): bool, group: ?string, order: int}> */
    private array $items = [];

    /**
     * @param  string  $label  Translation key.
     * @param  string  $active  Route name pattern marking the entry as current (e.g. "ticketing.desk.*").
     * @param  Closure(User): bool  $visible
     * @param  ?string  $group  One of the group constants; null for a link of its own.
     * @param  int  $order  Position of a link of its own among the links and groups; inside a group, entries keep the order they were added in.
     */
    public function add(string $label, string $route, string $active, Closure $visible, ?string $group = null, int $order = 60): void
    {
        if ($group !== null && ! isset($this->groups[$group])) {
            throw new InvalidArgumentException("Unknown navigation group [{$group}].");
        }

        $this->items[] = ['label' => $label, 'route' => $route, 'active' => $active, 'visible' => $visible, 'group' => $group, 'order' => $order];
    }

    /**
     * What the user sees, in order. A group with a single visible entry is shown as that link.
     *
     * @return list<array{label: string, url: string, active: string}|array{label: string, items: non-empty-list<array{label: string, url: string, active: string}>}>
     */
    public function for(User $user): array
    {
        $entries = [];
        $grouped = [];

        foreach ($this->items as $position => $item) {
            if (! ($item['visible'])($user)) {
                continue;
            }

            $link = ['label' => __($item['label']), 'url' => route($item['route']), 'active' => $item['active']];

            if ($item['group'] === null) {
                $entries[] = ['order' => $item['order'], 'position' => $position, 'entry' => $link];
            } else {
                $grouped[$item['group']]['links'][] = $link;
                $grouped[$item['group']]['position'] ??= $position;
            }
        }

        foreach ($grouped as $key => $group) {
            $entries[] = [
                'order' => $this->groups[$key]['order'],
                'position' => $group['position'],
                'entry' => count($group['links']) === 1 ? $group['links'][0] : ['label' => __($this->groups[$key]['label']), 'items' => $group['links']],
            ];
        }

        usort($entries, fn (array $a, array $b): int => [$a['order'], $a['position']] <=> [$b['order'], $b['position']]);

        return array_map(fn (array $entry): array => $entry['entry'], $entries);
    }
}
