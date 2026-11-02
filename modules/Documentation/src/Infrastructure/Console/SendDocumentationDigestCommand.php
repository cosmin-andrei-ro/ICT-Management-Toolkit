<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Documentation\Application\Reminders\SendDocumentationDigest;

#[Signature('documentation:digest')]
#[Description('Email space owners the pages to approve and to review')]
final class SendDocumentationDigestCommand extends Command
{
    public function handle(SendDocumentationDigest $digest): int
    {
        $this->components->info($digest->handle().' owner(s) emailed.');

        return self::SUCCESS;
    }
}
