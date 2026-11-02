<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    // Attach the monthly report as a PDF (when the PDF service runs); otherwise the email only links to it.
    'attach_pdf' => (bool) env('REPORTING_ATTACH_PDF', true),
];
