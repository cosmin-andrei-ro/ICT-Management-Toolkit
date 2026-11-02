<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Modules\Core\Domain\Models\Attachment;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shows an application's diagram image in the documentation sheet. Only
 * raster images of an application are served inline; everything else is
 * downloaded through Core's attachment controller.
 */
final readonly class DiagramImageController
{
    /** Never SVG: it can carry scripts. */
    public const array IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    public function __construct(
        private Gate $gate,
        private Filesystem $filesystem,
    ) {}

    public function __invoke(Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->attachable_type === 'applications.application' && in_array($attachment->mime_type, self::IMAGE_TYPES, true), 404);
        $this->gate->authorize('view', $attachment);

        return $this->filesystem->disk($attachment->disk)->response($attachment->path, null, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
