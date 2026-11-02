<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Home;

/**
 * Livewire components modules show on the home page (the Reporting dashboard),
 * so Core needs no knowledge of them. Each component decides what its viewer
 * may see.
 */
final class HomePanels
{
    /** @var array<string, int> */
    private array $panels = [];

    /**
     * @param  class-string  $component  A Livewire component.
     */
    public function add(string $component, int $order = 50): void
    {
        $this->panels[$component] = $order;
    }

    /**
     * @return list<class-string>
     */
    public function all(): array
    {
        $panels = $this->panels;
        asort($panels);

        /** @var list<class-string> */
        return array_keys($panels);
    }
}
