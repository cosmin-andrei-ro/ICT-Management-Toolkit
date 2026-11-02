<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Risk\Domain\Enums\RiskLevel;

/**
 * The organization's scoring method (owner's decision: 5 × 5, configurable):
 * what each likelihood and impact level means, the bands of likelihood ×
 * impact, from which score accepting a risk needs a risk manager, and how
 * often risks are reassessed. One row per organization; defaults until saved.
 *
 * @property string $organization_id
 * @property list<array{label: string, description: string}> $likelihood_levels
 * @property list<array{label: string, description: string}> $impact_levels
 * @property int $medium_from
 * @property int $high_from
 * @property int $critical_from
 * @property int $approval_from
 * @property int $review_months
 * @property ?string $updated_by
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
#[Table('risk_settings')]
final class RiskSettings extends Model
{
    use Auditable;
    use BelongsToOrganization;

    protected $primaryKey = 'organization_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The current organization's method, or the defaults when never changed.
     */
    public static function current(): self
    {
        return self::query()->first() ?? self::defaults();
    }

    public static function defaults(): self
    {
        $settings = new self;
        $settings->forceFill([
            'likelihood_levels' => self::levels('likelihood'),
            'impact_levels' => self::levels('impact'),
            'medium_from' => 5,
            'high_from' => 10,
            'critical_from' => 17,
            'approval_from' => 10,
            'review_months' => 12,
        ]);

        return $settings;
    }

    public function level(int $score): RiskLevel
    {
        return match (true) {
            $score >= $this->critical_from => RiskLevel::Critical,
            $score >= $this->high_from => RiskLevel::High,
            $score >= $this->medium_from => RiskLevel::Medium,
            default => RiskLevel::Low,
        };
    }

    /**
     * @param  'likelihood'|'impact'  $axis
     */
    public function levelLabel(string $axis, int $value): string
    {
        $levels = $axis === 'likelihood' ? $this->likelihood_levels : $this->impact_levels;

        return $levels[$value - 1]['label'] ?? (string) $value;
    }

    /**
     * @param  'likelihood'|'impact'  $axis
     * @return list<array{label: string, description: string}>
     */
    private static function levels(string $axis): array
    {
        $levels = [];

        foreach (range(1, 5) as $level) {
            $levels[] = [
                'label' => (string) __("risk::settings.defaults.{$axis}.{$level}.label"),
                'description' => (string) __("risk::settings.defaults.{$axis}.{$level}.description"),
            ];
        }

        return $levels;
    }

    protected function casts(): array
    {
        return [
            'likelihood_levels' => 'array',
            'impact_levels' => 'array',
            'medium_from' => 'integer',
            'high_from' => 'integer',
            'critical_from' => 'integer',
            'approval_from' => 'integer',
            'review_months' => 'integer',
        ];
    }
}
