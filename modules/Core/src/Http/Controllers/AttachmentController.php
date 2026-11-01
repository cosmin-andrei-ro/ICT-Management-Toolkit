<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Modules\Core\Domain\Models\Attachment;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class AttachmentController
{
    public function __construct(
        private Gate $gate,
        private Filesystem $filesystem,
    ) {}

    public function __invoke(Attachment $attachment): StreamedResponse
    {
        $this->gate->authorize('view', $attachment);

        // Always a download, never rendered inline; the sandbox CSP neutralizes any
        // active content should a browser render it anyway.
        return $this->filesystem->disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_ATTACHMENT,
                    $attachment->original_name,
                    $this->asciiFallback($attachment->original_name),
                ),
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    private function asciiFallback(string $name): string
    {
        $ascii = (string) preg_replace('/[^\x20-\x7E]/', '_', $name);

        return str_replace(['%', '/', '\\'], '_', $ascii);
    }
}
