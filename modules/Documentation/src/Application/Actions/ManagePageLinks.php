<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\PageLink;

/**
 * What a page is about: records of other modules, resolved through Core's
 * LinkedRecords as the actor sees them (nobody links what they cannot open).
 * Audited on the page.
 */
final readonly class ManagePageLinks
{
    public function __construct(
        private LinkedRecords $records,
        private AuditTrail $trail,
    ) {}

    /**
     * @throws InvalidArgumentException for a record the actor may not see, or already linked
     */
    public function link(Page $page, string $type, string $id, User $actor): PageLink
    {
        $record = $this->records->resolve($type, [$id], $actor)[0] ?? throw new InvalidArgumentException('Unknown record.');

        if ($page->links()->where('linked_type', $type)->where('linked_id', $record->id)->exists()) {
            throw new InvalidArgumentException('Already linked.');
        }

        $link = new PageLink;
        $link->forceFill([
            'page_id' => $page->id,
            'linked_type' => $type,
            'linked_id' => $record->id,
            'title' => mb_substr($record->title, 0, 255),
            'created_by' => $actor->id,
        ])->save();
        $this->trail->record(AuditEvent::Updated, $page, null, ['link_added' => $link->title], $actor->id);

        return $link;
    }

    public function unlink(Page $page, string $linkId, User $actor): void
    {
        $link = $page->links()->find($linkId);

        if ($link !== null) {
            $link->delete();
            $this->trail->record(AuditEvent::Updated, $page, ['link_removed' => $link->title], null, $actor->id);
        }
    }
}
