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
use Modules\Core\Application\Pdf\PdfRenderer;
use Modules\Core\Application\Pdf\PdfUnavailable;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Symfony\Component\HttpFoundation\HeaderUtils;
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
        private PdfRenderer $pdf,
        private AuditTrail $trail,
    ) {}

    public function show(): View
    {
        $this->gate->authorize('viewAny', Framework::class);

        return view('audit::report.page', [...$this->report->data(CarbonImmutable::today()), 'document' => 'report', 'pdf' => false, 'pdfAvailable' => $this->pdf->isAvailable()]);
    }

    public function pdf(Request $request): Response
    {
        $this->gate->authorize('viewAny', Framework::class);

        return $this->download($request, view('audit::report.page', [...$this->report->data(CarbonImmutable::today()), 'document' => 'report', 'pdf' => true, 'pdfAvailable' => true, 'styles' => $this->pdf->stylesheet()])->render(), 'compliance-report', route('audit.report'), ['report' => 'compliance']);
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

        return $this->download($request, $html, 'statement-of-applicability-'.Str::slug($framework->name), route('audit.frameworks.soa', $framework), ['report' => 'statement_of_applicability', 'framework' => $framework->name]);
    }

    /**
     * @param  array<string, string>  $recorded
     */
    private function download(Request $request, string $html, string $name, string $back, array $recorded): Response
    {
        try {
            $content = $this->pdf->render($html);
        } catch (PdfUnavailable) {
            return redirect($back)->with('status', __('audit::report.pdf_unavailable'));
        }

        $user = $request->user();
        $this->trail->record(AuditEvent::Exported, null, null, [...$recorded, 'format' => 'pdf'], $user instanceof User ? $user->id : null);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name.'-'.now()->format('Y-m-d').'.pdf'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
