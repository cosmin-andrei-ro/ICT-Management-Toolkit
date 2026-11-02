<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One cell of an assessment's impact matrix. Written by SaveAssessment only;
 * the assessment's audit entry records the matrix as a whole.
 *
 * @property string $assessment_id
 * @property string $organization_id
 * @property string $category_id
 * @property string $horizon_id
 * @property int $score
 */
#[Table('bia_impact_scores')]
final class ImpactScore extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'assessment_id';

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }
}
