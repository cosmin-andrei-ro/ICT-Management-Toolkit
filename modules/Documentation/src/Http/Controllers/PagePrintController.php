<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Application\Pdf\PdfDownload;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\PageVersion;
use Symfony\Component\HttpFoundation\Response;

/**
 * The published version of a page on paper, with who approved it: to print,
 * or as a PDF (recorded as an export).
 */
final readonly class PagePrintController
{
    public function __construct(
        private Gate $gate,
        private PdfDownload $pdf,
    ) {}

    public function show(Page $page): View
    {
        $this->authorizePublished($page);

        return view('documentation::print.page', [...$this->data($page), 'pdf' => false, 'pdfAvailable' => $this->pdf->isAvailable()]);
    }

    public function pdf(Page $page, Request $request): Response
    {
        $this->authorizePublished($page);
        $html = view('documentation::print.page', [...$this->data($page), 'pdf' => true, 'pdfAvailable' => true, 'styles' => $this->pdf->stylesheet()])->render();
        $user = $request->user();

        return $this->pdf->respond($html, Str::slug($page->displayTitle()).'-v'.$page->published_version, route('documentation.pages.print', $page), $page, ['version' => (string) $page->published_version], $user instanceof User ? $user : null);
    }

    private function authorizePublished(Page $page): void
    {
        $this->gate->authorize('view', $page);
        abort_unless($page->isPublished(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Page $page): array
    {
        $page->load('space');

        return [
            'page' => $page,
            'record' => PageVersion::query()->with(['author', 'approver'])->where('page_id', $page->id)->where('version', $page->published_version)->first(),
            'organization' => Organization::query()->find($page->organization_id),
            'timezone' => config()->string('app.display_timezone'),
        ];
    }
}
