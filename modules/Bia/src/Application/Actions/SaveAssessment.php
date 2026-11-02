<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Bia\Application\CurrentMatrix;
use Modules\Bia\Domain\Exceptions\AssessmentLocked;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BiaSettings;
use Modules\Bia\Domain\Models\ImpactScore;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;

/**
 * Saves an open assessment: the impact matrix and the recovery objectives. The
 * MTPD follows from the scores unless a different one is given with a reason.
 * The RTO may not exceed the MTPD. A draft may be incomplete; SubmitAssessment
 * checks completeness.
 */
final readonly class SaveAssessment
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditTrail $trail,
    ) {}

    /**
     * @param  array<array-key, mixed>  $scores  Keyed "categoryId|horizonId", 1–5.
     * @param  array{mtpd_override?: ?int, mtpd_reason?: ?string, rto_minutes?: ?int, rpo_minutes?: ?int, mbco?: ?string, workarounds?: ?string, minimum_staff?: ?int, notes?: ?string}  $fields
     *
     * @throws AssessmentLocked once submitted, approved or superseded
     * @throws ValidationException keyed "rto_minutes" or "mtpd_reason"
     */
    public function handle(Assessment $assessment, array $scores, array $fields): Assessment
    {
        return $this->db->transaction(function () use ($assessment, $scores, $fields): Assessment {
            $assessment = Assessment::query()->lockForUpdate()->findOrFail($assessment->id);

            if (! $assessment->status->isEditable()) {
                throw new AssessmentLocked;
            }

            $matrix = CurrentMatrix::load();
            $clean = $matrix->clean($scores);
            $computed = $matrix->read($clean)->mtpd(BiaSettings::current()->unacceptable_level);

            $override = $fields['mtpd_override'] ?? null;
            $reason = self::blankToNull($fields['mtpd_reason'] ?? null);

            if ($override !== null && $override !== $computed && $reason === null) {
                throw ValidationException::withMessages(['mtpd_reason' => __('bia::assessments.errors.mtpd_reason')]);
            }

            $mtpd = $override !== null && $override !== $computed ? $override : $computed;
            $rto = $fields['rto_minutes'] ?? null;

            if ($rto !== null && $mtpd !== null && $rto > $mtpd) {
                throw ValidationException::withMessages(['rto_minutes' => __('bia::assessments.errors.rto_over_mtpd')]);
            }

            $assessment->forceFill([
                'mtpd_minutes' => $mtpd,
                'mtpd_reason' => $mtpd !== $computed ? $reason : null,
                'rto_minutes' => $rto,
                'rpo_minutes' => $fields['rpo_minutes'] ?? null,
                'mbco' => self::blankToNull($fields['mbco'] ?? null),
                'workarounds' => self::blankToNull($fields['workarounds'] ?? null),
                'minimum_staff' => $fields['minimum_staff'] ?? null,
                'notes' => self::blankToNull($fields['notes'] ?? null),
            ])->save();

            $before = $assessment->scoreMap();

            if ($before !== $clean) {
                ImpactScore::query()->where('assessment_id', $assessment->id)->delete();
                ImpactScore::query()->insert(array_map(fn (string $key, int $score): array => [
                    'assessment_id' => $assessment->id,
                    'organization_id' => $assessment->organization_id,
                    'category_id' => explode('|', $key)[0],
                    'horizon_id' => explode('|', $key)[1],
                    'score' => $score,
                ], array_keys($clean), $clean));

                // Scores are one table; their changes are recorded on the assessment.
                $this->trail->record(AuditEvent::Updated, $assessment, ['impact_scores' => $before], ['impact_scores' => $clean]);
            }

            return $assessment;
        });
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
