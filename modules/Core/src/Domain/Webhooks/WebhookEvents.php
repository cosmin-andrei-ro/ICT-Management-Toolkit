<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Webhooks;

/**
 * The event types webhooks can subscribe to, registered by the modules that
 * publish them (ADR 0066), e.g. "ticket.created".
 */
final class WebhookEvents
{
    /** @var array<string, string> type => label translation key */
    private array $types = [];

    public function register(string $type, string $label): void
    {
        $this->types[$type] = $label;
    }

    /**
     * @param  array<string, string>  $types  type => label translation key
     */
    public function registerMany(array $types): void
    {
        foreach ($types as $type => $label) {
            $this->register($type, $label);
        }
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->types;
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }
}
