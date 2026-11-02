<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

/**
 * A module's view of an organization changed in a way that may suggest risks
 * (an application's documentation came into force), so the suggestions are
 * refreshed now rather than at the next daily run.
 */
final readonly class RiskSignalsChanged
{
    public function __construct(public string $organizationId) {}
}
