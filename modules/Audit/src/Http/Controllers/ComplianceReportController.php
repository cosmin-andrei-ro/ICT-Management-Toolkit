<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Audit\Application\Reports\ComplianceReport;
use Modules\Audit\Domain\Models\Framework;
use Modules\Core\Application\Pdf\PdfDownload;
use Modules\Core\Domain\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * The compliance report and each framework's Statement of Applicability, to
 * print and as PDF (recorded as exports).
 */
final readonly class ComplianceReportController
{
    public function __construct(
        private Gate $gate,
        private ComplianceReport $report,
        private PdfDownload $pdf,
    ) {}

    public function show(): View
    {
        $this->gate->authorize('viewAny', Framework::class);

        return view('audit::report.page', [...$this->report->data(CarbonImmutable::today()), 'document' => 'report', 'pdf' => false, 'pdfAvailable' => $this->pdf->isAvailable()]);
    }

    public function pdf(Request $request): Response
    {
        $this->gate->authorize('viewAny', Framework::class);

        $html = view('audit::report.page', [...$this->report->data(CarbonImmutable::today()), 'document' => 'report', 'pdf' => true, 'pdfAvailable' => true, 'styles' => $this->pdf->stylesheet()])->render();

        return $this->pdf->respond($html, 'compliance-report-'.now()->format('Y-m-d'), route('audit.report'), null, ['report' => 'compliance'], self::actor($request));
    }

    public function soa(Framework $framework): View
    {
        $this->gate->authorize('view', $framework);

        return view('audit::report.page', [...$this->report->statementOfApplicability($framework, CarbonImmutable::today()), 'document' => 'soa', 'pdf' => false, 'pdfAvailable' => $this->pdf->isAvailable()]);
    }

    public function soaPdf(Request $request, Framework $framework): Response
    {
        $this->gate->authorize('view', $framework);
        $html = view('audit::report.page', [...$this->report->statementOfApplicability($framework, CarbonImmutable::today()), 'document' => 'soa', 'pdf' => true, 'pdfAvailable' => true, 'styles' => $this->pdf->stylesheet()])->render();

        return $this->pdf->respond($html, 'statement-of-applicability-'.Str::slug($framework->name).'-'.now()->format('Y-m-d'), route('audit.frameworks.soa', $framework), $framework, ['report' => 'statement_of_applicability'], self::actor($request));
    }

    private static function actor(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
