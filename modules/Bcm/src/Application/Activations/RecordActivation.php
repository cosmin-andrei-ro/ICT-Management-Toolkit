<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Activations;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Bcm\Domain\Models\Activation;
use Modules\Core\Domain\Models\User;

/**
 * Updates an open activation: when the service was back, the summary and the
 * lessons learned; closing it keeps it as a record that never changes.
 */
final readonly class RecordActivation
{
    /**
     * @throws InvalidArgumentException once closed, for a restoration before the activation or in the future, or when closing without it
     */
    public function handle(Activation $activation, ?CarbonImmutable $restoredAt, ?string $summary, ?string $lessons, bool $close, User $actor): void
    {
        if (! $activation->isOpen()) {
            throw new InvalidArgumentException('The activation is closed.');
        }

        // Eloquent stores the wall-clock time without its offset.
        $restoredAt = $restoredAt?->setTimezone(date_default_timezone_get());

        if ($restoredAt !== null && ($restoredAt->lt($activation->activated_at) || $restoredAt->isFuture())) {
            throw new InvalidArgumentException('The service cannot be back before the activation or in the future.');
        }

        if ($close && $restoredAt === null) {
            throw new InvalidArgumentException('An activation is closed once the service is back.');
        }

        // Audited by the activation's own trail.
        $activation->forceFill([
            'restored_at' => $restoredAt,
            'summary' => self::blankToNull($summary),
            'lessons' => self::blankToNull($lessons),
            'closed_at' => $close ? now() : null,
            'closed_by' => $close ? $actor->id : null,
        ])->save();
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
