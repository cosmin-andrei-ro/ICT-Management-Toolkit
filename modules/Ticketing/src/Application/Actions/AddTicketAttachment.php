<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Http\UploadedFile;
use Modules\Core\Application\Collaboration\StoreAttachment;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Models\Ticket;

final readonly class AddTicketAttachment
{
    public function __construct(
        private TicketLifecycle $lifecycle,
        private StoreAttachment $storeAttachment,
    ) {}

    public function handle(Ticket $ticket, User $uploader, UploadedFile $file, bool $internal): Attachment
    {
        $this->lifecycle->assertNotFinal($ticket);

        return $this->storeAttachment->handle($ticket, $uploader, $file, $internal);
    }
}
