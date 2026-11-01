<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Audit;

/**
 * Metadata of the HTTP request being served, captured by middleware. Stays empty
 * for console commands and queued jobs, so their audit entries carry no client data.
 */
final class RequestContext
{
    public ?string $ipAddress = null;

    public ?string $userAgent = null;

    public ?string $requestId = null;
}
