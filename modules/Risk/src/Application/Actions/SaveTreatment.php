<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Domain\Enums\AcceptanceStatus;
use Modules\Risk\Domain\Enums\Treatment;
use Modules\Risk\Domain\Models\Acceptance;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\ScoreRecord;

/**
 * How a risk is treated (ISO/IEC 27005: mitigate, transfer, avoid or accept),
 * the plan, and the residual risk expected once treated. A different residual
 * risk voids the acceptance given and withdraws one waiting for approval.
 * Audited by the risk's own trail.
 */
final readonly class SaveTreatment
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @throws InvalidArgumentException for a residual score outside 1–5, or only half of it
     */
    public function handle(Risk $risk, ?Treatment $treatment, ?string $plan, ?int $likelihood, ?int $impact, User $actor): void
    {
        if (($likelihood === null) !== ($impact === null) || ($likelihood !== null && ($likelihood < 1 || $likelihood > 5 || $impact < 1 || $impact > 5))) {
            throw new InvalidArgumentException('Residual likelihood and impact go together, from 1 to 5.');
        }

        $this->db->transaction(function () use ($risk, $treatment, $plan, $likelihood, $impact, $actor): void {
            $locked = Risk::query()->lockForUpdate()->findOrFail($risk->id);
            $changed = $locked->residual_likelihood !== $likelihood || $locked->residual_impact !== $impact;
            $plan = $plan !== null ? trim($plan) : '';

            $locked->forceFill([
                'treatment' => $treatment,
                'treatment_plan' => $plan !== '' ? $plan : null,
                'residual_likelihood' => $likelihood,
                'residual_impact' => $impact,
                'accepted_until' => $changed ? null : $locked->accepted_until,
            ])->save();

            if ($changed) {
                Acceptance::query()->where('risk_id', $locked->id)->where('status', AcceptanceStatus::Pending)->update(['status' => AcceptanceStatus::Withdrawn->value]);
                Acceptance::query()->where('risk_id', $locked->id)->where('status', AcceptanceStatus::Approved)->whereNotNull('valid_until')->where('valid_until', '>=', today()->toDateString())
                    ->update(['status' => AcceptanceStatus::Lapsed->value]);
                ScoreRecord::capture($locked, $actor->id);
            }
        });

        $risk->refresh();
    }
}
