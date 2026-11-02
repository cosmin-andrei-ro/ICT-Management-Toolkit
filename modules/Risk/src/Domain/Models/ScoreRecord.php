<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Models\User;

/**
 * The scores of a risk at one moment; appended whenever they change.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $risk_id
 * @property ?int $inherent_likelihood
 * @property ?int $inherent_impact
 * @property ?int $residual_likelihood
 * @property ?int $residual_impact
 * @property ?string $recorded_by
 * @property Carbon $recorded_at
 * @property-read ?User $recorder
 */
#[Table('risk_score_history')]
final class ScoreRecord extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    public $timestamps = false;

    /**
     * Appends the risk's current scores, unless they equal the last record.
     */
    public static function capture(Risk $risk, ?string $userId): void
    {
        $scores = [
            'inherent_likelihood' => $risk->inherent_likelihood,
            'inherent_impact' => $risk->inherent_impact,
            'residual_likelihood' => $risk->residual_likelihood,
            'residual_impact' => $risk->residual_impact,
        ];
        $last = self::query()->where('risk_id', $risk->id)->latest('recorded_at')->first();

        if ($last !== null && $last->only(array_keys($scores)) === $scores) {
            return;
        }

        (new self)->forceFill([...$scores, 'organization_id' => $risk->organization_id, 'risk_id' => $risk->id, 'recorded_by' => $userId, 'recorded_at' => now()])->save();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function casts(): array
    {
        return [
            'inherent_likelihood' => 'integer',
            'inherent_impact' => 'integer',
            'residual_likelihood' => 'integer',
            'residual_impact' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }
}
