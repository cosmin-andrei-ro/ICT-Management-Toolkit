<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\Export;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ExportController
{
    public function __construct(
        private Gate $gate,
        private Filesystem $filesystem,
        private AuditTrail $trail,
    ) {}

    public function __invoke(Export $export): StreamedResponse
    {
        $this->gate->authorize('download', $export);

        $this->trail->record(AuditEvent::ExportDownloaded, $export, null, ['type' => $export->type, 'format' => $export->format->value]);

        $filename = (string) $export->filename;

        return $this->filesystem->disk((string) $export->disk)->download((string) $export->path, $filename, [
            'Content-Type' => $export->format->mimeType(),
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, (string) preg_replace('/[^\x20-\x7E]|[%\/\\\\]/', '_', $filename)),
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
