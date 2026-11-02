<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Core\Application\Pdf\PdfRenderer;
use Modules\Core\Application\Pdf\PdfUnavailable;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Reporting\Application\MonthlyReport;
use Modules\Reporting\Application\Notifications\MonthlyReportReady;
use Modules\Reporting\Domain\Models\ReportSubscription;

/**
 * On the first of the month, the report of the month that ended goes to each
 * organization's subscribers who may still see it.
 */
final readonly class SendMonthlyReports
{
    public function __construct(
        private OrganizationContext $context,
        private MonthlyReport $report,
        private PdfRenderer $renderer,
    ) {}

    /**
     * @return int People emailed.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $month = $today->startOfMonth()->subMonthNoOverflow();
        $sent = 0;

        foreach (Organization::query()->orderBy('id')->get(['id', 'name']) as $organization) {
            $sent += $this->context->runAs($organization->id, function () use ($organization, $month, $today): int {
                $subscribers = ReportSubscription::query()->with('user')->get()
                    ->map(fn (ReportSubscription $subscription) => $subscription->user)
                    // Someone who lost the permission or the account stops receiving it.
                    ->filter(fn ($user): bool => $user->canSignIn() && $user->can(ManageReportSubscriptions::VIEW));

                if ($subscribers->isEmpty()) {
                    return 0;
                }

                $data = $this->report->build($month, $today);
                $notification = new MonthlyReportReady(
                    $organization->name,
                    $month->isoFormat('MMMM YYYY'),
                    $data['statuses'],
                    route('reporting.report.show', ['month' => $month->format('Y-m')]),
                    $this->pdf($data, $organization),
                    'management-report-'.$month->format('Y-m').'.pdf',
                );

                foreach ($subscribers as $user) {
                    $user->notify($notification);
                }

                return $subscribers->count();
            });
        }

        return $sent;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pdf(array $data, Organization $organization): ?string
    {
        if (config('reporting.report.attach_pdf') !== true || ! $this->renderer->isAvailable()) {
            return null;
        }

        try {
            $html = view('reporting::print.monthly', [...$data, 'organization' => $organization, 'pdf' => true, 'pdfAvailable' => true, 'styles' => $this->renderer->stylesheet()])->render();

            return base64_encode($this->renderer->render($html));
        } catch (PdfUnavailable) {
            // The link still reaches the report.
            return null;
        }
    }
}
