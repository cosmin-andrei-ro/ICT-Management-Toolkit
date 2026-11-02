<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Core\Application\Pdf\PdfDownload;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Reporting\Application\Actions\ManageReportSubscriptions;
use Modules\Reporting\Application\MonthlyReport;
use Symfony\Component\HttpFoundation\Response;

/**
 * The monthly management report on paper, or as a PDF recorded as an export.
 */
final readonly class MonthlyReportController
{
    public function __construct(
        private MonthlyReport $report,
        private PdfDownload $pdf,
        private OrganizationContext $context,
    ) {}

    public function show(Request $request): View
    {
        $month = $this->month($request);

        return view('reporting::print.monthly', [...$this->data($month), 'pdf' => false, 'pdfAvailable' => $this->pdf->isAvailable()]);
    }

    public function pdf(Request $request): Response
    {
        $month = $this->month($request);
        $html = view('reporting::print.monthly', [...$this->data($month), 'pdf' => true, 'pdfAvailable' => true, 'styles' => $this->pdf->stylesheet()])->render();
        $user = $request->user();

        return $this->pdf->respond($html, 'management-report-'.$month->format('Y-m'), route('reporting.report.show', ['month' => $month->format('Y-m')]), null, ['report' => 'management', 'month' => $month->format('Y-m')], $user instanceof User ? $user : null);
    }

    private function month(Request $request): CarbonImmutable
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can(ManageReportSubscriptions::VIEW), 403);
        $value = $request->query('month');

        if ($value === null) {
            return CarbonImmutable::today()->startOfMonth()->subMonthNoOverflow();
        }

        $month = is_string($value) ? MonthlyReport::month($value) : null;
        abort_if($month === null, 404);

        return $month;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(CarbonImmutable $month): array
    {
        return [...$this->report->build($month), 'organization' => Organization::query()->find($this->context->requireId())];
    }
}
