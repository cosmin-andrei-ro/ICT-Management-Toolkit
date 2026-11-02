<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Applications\Application\Sheets\SheetDocument;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Core\Application\Pdf\PdfDownload;
use Modules\Core\Domain\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * The full documentation of a version, laid out as the template: a page to
 * print, and a PDF for audit (recorded in the audit trail).
 */
final readonly class SheetDocumentController
{
    public function __construct(
        private Gate $gate,
        private SheetDocument $document,
        private PdfDownload $pdf,
    ) {}

    public function show(Sheet $sheet, Request $request): View
    {
        $this->gate->authorize('view', $sheet);

        return view('applications::document', [...$this->document->data($sheet, $this->viewer($request), false), 'pdfAvailable' => $this->pdf->isAvailable()]);
    }

    public function pdf(Sheet $sheet, Request $request): Response
    {
        $this->gate->authorize('view', $sheet);
        $viewer = $this->viewer($request);

        $html = view('applications::document', [...$this->document->data($sheet, $viewer, true), 'pdfAvailable' => true, 'styles' => $this->pdf->stylesheet()])->render();

        return $this->pdf->respond($html, Str::slug($sheet->application->code.'-'.($sheet->versionLabel() ?? 'draft')), route('applications.sheets.document', $sheet), $sheet, [], $viewer);
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
