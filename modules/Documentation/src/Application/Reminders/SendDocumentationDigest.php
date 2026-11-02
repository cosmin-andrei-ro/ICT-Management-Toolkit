<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Reminders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Documentation\Application\Notifications\DocumentationDigest;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\Space;

/**
 * Weekly, per space owner: pages of their spaces waiting for their approval,
 * and pages overdue or due for review within 30 days. Nothing is sent when
 * there is nothing to do.
 */
final readonly class SendDocumentationDigest
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * @return array{to_approve: int, review_overdue: int, review_due: int}
     */
    public function figures(User $owner, CarbonImmutable $today): array
    {
        $spaces = Space::query()->whereHas('owners', fn ($query) => $query->whereKey($owner->id))->pluck('id');
        $published = fn () => Page::query()->whereIn('space_id', $spaces)->whereNotNull('published_version');

        return [
            // Never their own work: approval is by someone else (ADR 0041).
            'to_approve' => Page::query()->whereIn('space_id', $spaces)->whereNotNull('pending_version')
                ->where(fn ($query) => $query->whereNull('submitted_by')->orWhere('submitted_by', '!=', $owner->id))
                ->whereNotExists(fn (QueryBuilder $query) => $query->from('documentation_page_versions')
                    ->whereColumn('documentation_page_versions.page_id', 'documentation_pages.id')
                    ->whereColumn('documentation_page_versions.version', 'documentation_pages.pending_version')
                    ->where('documentation_page_versions.author_id', $owner->id))
                ->count(),
            'review_overdue' => $published()->where('review_on', '<', $today->toDateString())->count(),
            'review_due' => $published()->whereBetween('review_on', [$today->toDateString(), $today->addDays(30)->toDateString()])->count(),
        ];
    }

    /**
     * @return int People emailed.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $sent = 0;

        foreach (Organization::query()->orderBy('id')->get(['id']) as $organization) {
            $this->context->runAs($organization->id, function () use ($today, &$sent): void {
                $owners = User::query()->where('is_active', true)
                    ->whereIn('id', Space::query()->join('documentation_space_owners', 'documentation_space_owners.space_id', '=', 'documentation_spaces.id')->select('documentation_space_owners.user_id'))
                    ->get();

                foreach ($owners as $owner) {
                    $figures = array_filter($this->figures($owner, $today), fn (int $count): bool => $count > 0);

                    if ($figures !== [] && $owner->canSignIn()) {
                        $owner->notify(new DocumentationDigest($figures, route('documentation.index')));
                        $sent++;
                    }
                }
            });
        }

        return $sent;
    }
}
