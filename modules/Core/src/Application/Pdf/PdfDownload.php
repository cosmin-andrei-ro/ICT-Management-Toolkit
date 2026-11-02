<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Pdf;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * A printable page sent as a PDF download (ADR 0030): rendered through
 * Gotenberg, recorded as an export in the audit trail, or back to the page
 * with a message when the PDF service is not available.
 */
final readonly class PdfDownload
{
    public function __construct(
        private PdfRenderer $renderer,
        private AuditTrail $trail,
    ) {}

    public function isAvailable(): bool
    {
        return $this->renderer->isAvailable();
    }

    /**
     * The compiled stylesheet to inline in the document.
     */
    public function stylesheet(): string
    {
        return $this->renderer->stylesheet();
    }

    /**
     * @param  string  $filename  Without extension.
     * @param  string  $fallbackUrl  The printable page, where the user is sent back when no PDF can be made.
     * @param  array<string, string>  $recorded  What the audit trail says was exported.
     */
    public function respond(string $html, string $filename, string $fallbackUrl, ?Model $subject, array $recorded, ?User $actor): Response
    {
        try {
            $content = $this->renderer->render($html);
        } catch (PdfUnavailable) {
            return redirect($fallbackUrl)->with('status', __('core::pdf.unavailable'));
        }

        $this->trail->record(AuditEvent::Exported, $subject, null, [...$recorded, 'format' => 'pdf'], $actor?->id);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename.'.pdf'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
