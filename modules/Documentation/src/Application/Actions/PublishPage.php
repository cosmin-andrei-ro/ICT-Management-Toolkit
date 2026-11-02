<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Documentation\Application\Notifications\PageUpdate;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\PageVersion;
use Modules\Documentation\Domain\Models\Space;

/**
 * Publishing (ADR 0041): in free spaces every saved version is published at
 * once; in controlled spaces an editor submits a version and a space owner
 * other than its author and the submitter approves it - or rejects it,
 * saying why. Publishing sets the next review date from the space's interval.
 */
final readonly class PublishPage
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditTrail $trail,
    ) {}

    /**
     * Makes a version what readers see.
     */
    public function publish(Page $page, int $version, ?User $approver): void
    {
        $record = PageVersion::query()->where('page_id', $page->id)->where('version', $version)->firstOrFail();
        $space = Space::query()->findOrFail($page->space_id);

        $page->forceFill([
            'published_title' => $record->title,
            'published_body' => $record->body,
            'published_version' => $record->version,
            'published_at' => now(),
            'pending_version' => null,
            'submitted_by' => null,
            'submitted_at' => null,
            'rejection_note' => null,
            'review_on' => CarbonImmutable::today()->addMonthsNoOverflow($space->review_months),
        ])->save();

        if ($approver !== null) {
            $record->forceFill(['approved_by' => $approver->id, 'approved_at' => now()])->save();
        }
    }

    /**
     * @throws InvalidArgumentException when there is nothing new to publish
     */
    public function submit(Page $page, User $actor): void
    {
        $this->db->transaction(function () use ($page, $actor): void {
            $locked = Page::query()->lockForUpdate()->findOrFail($page->id);

            if (! $locked->hasDraft()) {
                throw new InvalidArgumentException('Nothing to publish.');
            }

            $locked->forceFill(['pending_version' => $locked->version, 'submitted_by' => $actor->id, 'submitted_at' => now(), 'rejection_note' => null])->save();
            $this->trail->record(AuditEvent::Updated, $locked, null, ['submitted_version' => $locked->version], $actor->id);
        });

        $page->refresh();
        $notification = new PageUpdate('submitted', $page->title, route('documentation.pages.show', ['page' => $page, 'draft' => 1]));

        foreach (Space::query()->findOrFail($page->space_id)->owners()->get() as $owner) {
            if ($owner->canSignIn() && $owner->can('approve', $page)) {
                $owner->notify($notification);
            }
        }
    }

    /**
     * @throws InvalidArgumentException when no version waits for approval
     */
    public function approve(Page $page, User $approver): void
    {
        $this->decide($page, $approver, true, null);
    }

    /**
     * @throws InvalidArgumentException when no version waits for approval, or without a reason
     */
    public function reject(Page $page, User $approver, string $note): void
    {
        if (trim($note) === '') {
            throw new InvalidArgumentException('Say why.');
        }

        $this->decide($page, $approver, false, trim($note));
    }

    /**
     * The page was read through and is still right: the next review moves on.
     */
    public function markReviewed(Page $page, User $actor): void
    {
        $space = Space::query()->findOrFail($page->space_id);
        $page->forceFill(['review_on' => CarbonImmutable::today()->addMonthsNoOverflow($space->review_months)])->save();
        $this->trail->record(AuditEvent::Updated, $page, null, ['reviewed' => CarbonImmutable::today()->toDateString()], $actor->id);
    }

    private function decide(Page $page, User $approver, bool $approved, ?string $note): void
    {
        $submitter = $this->db->transaction(function () use ($page, $approver, $approved, $note): ?string {
            $locked = Page::query()->lockForUpdate()->findOrFail($page->id);

            if ($locked->pending_version === null) {
                throw new InvalidArgumentException('Nothing waits for approval.');
            }

            $submitter = $locked->submitted_by;
            $version = $locked->pending_version;

            if ($approved) {
                $this->publish($locked, $version, $approver);
            } else {
                $locked->forceFill(['pending_version' => null, 'submitted_by' => null, 'submitted_at' => null, 'rejection_note' => $note])->save();
            }

            $this->trail->record(AuditEvent::Updated, $locked, null, [($approved ? 'approved_version' : 'rejected_version') => $version], $approver->id);

            return $submitter;
        });

        $page->refresh();
        $recipient = $submitter !== null ? User::query()->find($submitter) : null;

        if ($recipient !== null && $recipient->canSignIn()) {
            $recipient->notify(new PageUpdate($approved ? 'approved' : 'rejected', $page->title, route('documentation.pages.show', $page), $note));
        }
    }
}
