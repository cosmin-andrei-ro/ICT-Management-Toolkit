<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Core\Application\Pdf\PdfRenderer;
use Modules\Core\Application\Pdf\PdfUnavailable;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Application\Reports\RiskReport;
use Modules\Risk\Domain\Models\Risk;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * The risk report to print, and as a PDF (recorded as an export).
 */
final readonly class RiskReportController
{
    public function __construct(
        private Gate $gate,
        private RiskReport $report,
        private PdfRenderer $pdf,
        private AuditTrail $trail,
    ) {}

    public function show(): View
    {
        $this->gate->authorize('viewAny', Risk::class);

        return view('risk::report.page', [...$this->report->data(), 'pdf' => false, 'pdfAvailable' => $this->pdf->isAvailable()]);
    }

    public function pdf(Request $request): Response
    {
        $this->gate->authorize('viewAny', Risk::class);
        $html = view('risk::report.page', [...$this->report->data(), 'pdf' => true, 'pdfAvailable' => true, 'styles' => $this->pdf->stylesheet()])->render();

        try {
            $content = $this->pdf->render($html);
        } catch (PdfUnavailable) {
            return redirect()->route('risk.report')->with('status', __('risk::report.pdf_unavailable'));
        }

        $user = $request->user();
        $this->trail->record(AuditEvent::Exported, null, null, ['report' => 'risk', 'format' => 'pdf'], $user instanceof User ? $user->id : null);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'risk-report-'.now()->format('Y-m-d').'.pdf'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
