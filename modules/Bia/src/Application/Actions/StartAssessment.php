<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Bia\Domain\Enums\AssessmentStatus;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Domain\Models\ImpactScore;
use Modules\Core\Domain\Models\User;

/**
 * Opens the next version of a process's assessment, or returns the one already
 * open. A new version starts from the approved one (scores, objectives and
 * dependencies), so a review changes only what changed.
 */
final readonly class StartAssessment
{
    public function __construct(
        private ConnectionInterface $db,
        private ManageDependencies $dependencies,
        private ProvisionDefaultMatrix $provision,
    ) {}

    public function handle(BusinessProcess $process, User $actor): Assessment
    {
        // An organization that never opened the BIA list still gets a matrix to score.
        $this->provision->handle();

        return $this->db->transaction(function () use ($process, $actor): Assessment {
            // Locked: two people opening the next version at once get the same one.
            $process = BusinessProcess::query()->lockForUpdate()->findOrFail($process->id);

            $open = Assessment::query()->where('process_id', $process->id)->whereIn('status', ['draft', 'submitted', 'returned'])->first();

            if ($open !== null) {
                return $open;
            }

            $approved = Assessment::query()->where('process_id', $process->id)->where('status', AssessmentStatus::Approved->value)->first();
            $latest = Assessment::query()->where('process_id', $process->id)->max('version');
            $version = (is_numeric($latest) ? (int) $latest : 0) + 1;

            $assessment = new Assessment;
            $assessment->forceFill([
                'organization_id' => $process->organization_id,
                'process_id' => $process->id,
                'version' => $version,
                'status' => AssessmentStatus::Draft,
                'rto_minutes' => $approved?->rto_minutes,
                'rpo_minutes' => $approved?->rpo_minutes,
                'mtpd_minutes' => $approved?->mtpd_minutes,
                'mtpd_reason' => $approved?->mtpd_reason,
                'mbco' => $approved?->mbco,
                'workarounds' => $approved?->workarounds,
                'minimum_staff' => $approved?->minimum_staff,
                'notes' => $approved?->notes,
            ])->save();

            if ($approved !== null) {
                $rows = ImpactScore::query()->where('assessment_id', $approved->id)->get()
                    ->map(fn (ImpactScore $score): array => [
                        'assessment_id' => $assessment->id,
                        'organization_id' => $assessment->organization_id,
                        'category_id' => $score->category_id,
                        'horizon_id' => $score->horizon_id,
                        'score' => $score->score,
                    ])->all();
                ImpactScore::query()->insert($rows);
                $this->dependencies->copy($approved, $assessment, $actor);
            }

            return $assessment;
        });
    }
}
