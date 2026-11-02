<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Suggestions;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Application\Actions\ManageRiskAssets;
use Modules\Risk\Application\Actions\SaveRisk;
use Modules\Risk\Application\Catalog\ProvisionCatalog;
use Modules\Risk\Domain\Enums\AssetType;
use Modules\Risk\Domain\Enums\RiskStatus;
use Modules\Risk\Domain\Enums\SuggestionStatus;
use Modules\Risk\Domain\Models\CatalogEntry;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\ScoreRecord;
use Modules\Risk\Domain\Models\Suggestion;

/**
 * A risk manager turns a suggestion into a risk, prefilled with what the
 * module knows (scenario, what it concerns, threats and vulnerabilities, the
 * scores it can tell), or dismisses it with a reason. Audited by the
 * suggestion's own trail; the risk by its own.
 */
final readonly class DecideSuggestion
{
    public function __construct(
        private ConnectionInterface $db,
        private SaveRisk $saveRisk,
        private ManageRiskAssets $assets,
        private ProvisionCatalog $catalog,
    ) {}

    /**
     * @throws InvalidArgumentException when the suggestion is no longer open, or the owner is not a member
     */
    public function convert(Suggestion $suggestion, string $ownerId, User $actor): Risk
    {
        if (! User::query()->whereKey($ownerId)->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($suggestion->organization_id))->exists()) {
            throw new InvalidArgumentException('The owner must be an active member of the organization.');
        }

        $this->catalog->handle();

        return $this->db->transaction(function () use ($suggestion, $ownerId, $actor): Risk {
            $locked = Suggestion::query()->lockForUpdate()->findOrFail($suggestion->id);

            if ($locked->status !== SuggestionStatus::Open) {
                throw new InvalidArgumentException('The suggestion is no longer open.');
            }

            $risk = $this->saveRisk->handle(null, [
                'title' => $locked->title,
                'description' => $locked->description,
                'category' => $locked->category,
                'owner_id' => $ownerId,
                'status' => RiskStatus::Open,
            ]);

            foreach ($locked->assets as $asset) {
                $type = AssetType::tryFrom($asset['type']);

                if ($type === null) {
                    continue;
                }

                try {
                    $this->assets->add($risk, $type, $asset['id'], $actor);
                } catch (InvalidArgumentException) {
                    // Gone since it was suggested: nothing to link.
                }
            }

            $entries = CatalogEntry::query()->where('is_active', true)->whereIn('name', [...$locked->threats, ...$locked->vulnerabilities])->pluck('id')->all();
            $risk->catalogEntries()->syncWithPivotValues(array_values(array_filter($entries, is_string(...))), ['organization_id' => $risk->organization_id]);

            if ($locked->likelihood !== null && $locked->impact !== null) {
                $risk->forceFill(['inherent_likelihood' => $locked->likelihood, 'inherent_impact' => $locked->impact])->save();
                ScoreRecord::capture($risk, $actor->id);
            }

            $locked->forceFill(['status' => SuggestionStatus::Converted, 'risk_id' => $risk->id, 'decided_by' => $actor->id, 'decided_at' => now()])->save();

            return $risk;
        });
    }

    /**
     * @throws InvalidArgumentException without a reason, or when no longer open
     */
    public function dismiss(Suggestion $suggestion, string $reason, User $actor): void
    {
        $reason = trim($reason);

        if ($reason === '' || $suggestion->status !== SuggestionStatus::Open) {
            throw new InvalidArgumentException('Say why, and only for an open suggestion.');
        }

        $suggestion->forceFill(['status' => SuggestionStatus::Dismissed, 'dismissal_reason' => mb_substr($reason, 0, 2000), 'decided_by' => $actor->id, 'decided_at' => now()])->save();
    }
}
