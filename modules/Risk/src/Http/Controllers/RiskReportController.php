<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Core\Application\Pdf\PdfDownload;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Application\Reports\RiskReport;
use Modules\Risk\Domain\Models\Risk;
use Symfony\Component\HttpFoundation\Response;

/**
 * The risk report to print, and as a PDF (recorded as an export).
 */
final readonly class RiskReportController
{
    public function __construct(
        private Gate $gate,
        private RiskReport $report,
        private PdfDownload $pdf,
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
        $user = $request->user();

        return $this->pdf->respond($html, 'risk-report-'.now()->format('Y-m-d'), route('risk.report'), null, ['report' => 'risk'], $user instanceof User ? $user : null);
    }
}
