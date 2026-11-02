<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Suggestions;

use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Risks\RiskSignal;
use Modules\Core\Domain\Risks\RiskSignals;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Domain\Enums\RiskCategory;
use Modules\Risk\Domain\Enums\SuggestionStatus;
use Modules\Risk\Domain\Models\Suggestion;

/**
 * Brings the current organization's suggestions in line with what the modules
 * signal now: a new situation opens a suggestion; an open one is updated, or
 * resolved when its situation is gone; dismissed and converted ones stay as
 * decided (a dismissed situation that comes back is not suggested again).
 */
final readonly class RefreshSuggestions
{
    public function __construct(
        private RiskSignals $signals,
        private ConnectionInterface $db,
        private OrganizationContext $context,
    ) {}

    /**
     * @return array{opened: int, resolved: int, failed: list<string>}
     */
    public function handle(): array
    {
        $organizationId = $this->context->requireId();
        ['signals' => $signals, 'failed' => $failed] = $this->signals->collect();
        $opened = 0;
        $seen = [];

        $resolved = $this->db->transaction(function () use ($signals, $failed, $organizationId, &$opened, &$seen): int {
            foreach ($signals as $signal) {
                if (RiskCategory::tryFrom($signal->category) === null || isset($seen[$signal->key])) {
                    continue;
                }

                $seen[$signal->key] = true;
                $suggestion = Suggestion::query()->where('key', $signal->key)->lockForUpdate()->first();

                if ($suggestion === null) {
                    $suggestion = new Suggestion;
                    $suggestion->forceFill(['organization_id' => $organizationId, 'key' => $signal->key, 'status' => SuggestionStatus::Open, 'first_seen_at' => now()]);
                    $opened++;
                } elseif ($suggestion->status === SuggestionStatus::Resolved) {
                    $suggestion->status = SuggestionStatus::Open;
                    $opened++;
                }

                if ($suggestion->status === SuggestionStatus::Open) {
                    $suggestion->forceFill(self::content($signal));
                }

                $suggestion->forceFill(['last_seen_at' => now()])->save();
            }

            // Situations gone (except those of a source that failed this time).
            return Suggestion::query()->where('status', SuggestionStatus::Open)
                ->whereNotIn('key', array_keys($seen))
                ->when($failed !== [], fn ($query) => $query->whereNotIn('source', $failed))
                ->update(['status' => SuggestionStatus::Resolved->value]);
        });

        return ['opened' => $opened, 'resolved' => $resolved, 'failed' => $failed];
    }

    /**
     * @return array<string, mixed>
     */
    private static function content(RiskSignal $signal): array
    {
        return [
            'source' => mb_substr(explode('.', $signal->key)[0], 0, 64),
            'source_label' => mb_substr($signal->source, 0, 255),
            'title' => mb_substr($signal->title, 0, 255),
            'description' => mb_substr($signal->description, 0, 5000),
            'category' => $signal->category,
            'assets' => $signal->assets,
            'threats' => $signal->threats,
            'vulnerabilities' => $signal->vulnerabilities,
            'likelihood' => $signal->likelihood,
            'impact' => $signal->impact,
            'evidence_url' => $signal->evidenceUrl,
            'owner_id' => $signal->ownerId,
        ];
    }
}
