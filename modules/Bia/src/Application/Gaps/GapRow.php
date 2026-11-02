<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Gaps;

use Modules\Core\Domain\Links\RecoveryProfile;

/**
 * One configuration item: the strictest recovery the business requires of it,
 * what IT declared it can deliver, and the processes behind the requirement.
 */
final readonly class GapRow
{
    public const string MET = 'met';

    public const string GAP = 'gap';

    /** IT has not declared what it can deliver. */
    public const string UNKNOWN = 'unknown';

    private const array CRITICALITY_WEIGHT = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    /**
     * @param  list<array{name: string, url: string, rto: int, rpo: ?int, direct: bool}>  $processes
     */
    public function __construct(
        public RecoveryProfile $item,
        public int $requiredRto,
        public ?int $requiredRpo,
        public array $processes,
        /** low, medium, high or critical, from the strictest tier. */
        public string $suggestedCriticality,
    ) {}

    public function rtoStatus(): string
    {
        return self::compare($this->item->recoveryTimeMinutes, $this->requiredRto);
    }

    public function rpoStatus(): string
    {
        return $this->requiredRpo === null ? self::MET : self::compare($this->item->recoveryPointMinutes, $this->requiredRpo);
    }

    public function status(): string
    {
        $statuses = [$this->rtoStatus(), $this->rpoStatus()];

        return match (true) {
            in_array(self::GAP, $statuses, true) => self::GAP,
            in_array(self::UNKNOWN, $statuses, true) => self::UNKNOWN,
            default => self::MET,
        };
    }

    public function criticalityDiffers(): bool
    {
        return $this->item->criticality !== $this->suggestedCriticality;
    }

    /**
     * Gaps first, then undeclared capabilities, then the rest; strictest first.
     */
    public function sortKey(): string
    {
        $order = [self::GAP => 0, self::UNKNOWN => 1, self::MET => 2][$this->status()];

        return sprintf('%d-%09d-%d-%s', $order, $this->requiredRto, 4 - (self::CRITICALITY_WEIGHT[$this->suggestedCriticality] ?? 0), mb_strtolower($this->item->name));
    }

    private static function compare(?int $capability, int $required): string
    {
        return match (true) {
            $capability === null => self::UNKNOWN,
            $capability > $required => self::GAP,
            default => self::MET,
        };
    }
}
