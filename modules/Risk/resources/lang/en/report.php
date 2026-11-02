<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'button' => 'Risk report',
    'title' => 'Risk report',
    'as_of' => 'As of :date',
    'overview' => 'Overview of open risks',
    'before' => 'Before treatment',
    'after' => 'After treatment',
    'unscored' => ':count open risk(s) not assessed yet are not shown on the maps.',
    'method' => 'Method',
    'method_line' => 'Risk = likelihood × impact (1 to 25). Medium from :medium, high from :high, critical from :critical. Accepting a residual risk from :approval needs a risk manager. Risks are reassessed every :months months.',
    'register' => 'Open risks, highest first',
    'print' => 'Print',
    'download_pdf' => 'Download PDF',
    'pdf_unavailable' => 'The PDF service is not available right now. Print the page or save it as PDF from the browser.',
    'export' => [
        'button' => 'Export to Excel',
        'of' => 'Risk register of :date',
        'risks' => 'Risks',
        'actions' => 'Treatment actions',
        'acceptances' => 'Acceptances',
        'reference' => 'Reference',
        'concerns' => 'Concerns',
        'inherent_level' => 'Inherent level',
        'residual_level' => 'Residual level',
        'accepted_until' => 'Accepted until',
        'threats_vulnerabilities' => 'Threats and vulnerabilities',
        'overdue' => 'Overdue',
        'ticket' => 'Ticket',
        'score' => 'Residual score',
        'requested_by' => 'Requested by',
        'requested_at' => 'Requested on',
        'decided_by' => 'Decided by',
        'decided_at' => 'Decided on',
        'valid_until' => 'Valid until',
        'comment' => 'Comment',
    ],
    'digest' => [
        'subject' => 'Risk register: what needs attention',
        'suggestions' => '{1} :count suggested risk waits for review.|[2,*] :count suggested risks wait for review.',
        'pending_acceptances' => '{1} :count risk acceptance waits for approval.|[2,*] :count risk acceptances wait for approval.',
        'acceptances_expiring' => '{1} :count risk acceptance expires within 30 days.|[2,*] :count risk acceptances expire within 30 days.',
        'overdue_actions' => '{1} :count treatment action is overdue.|[2,*] :count treatment actions are overdue.',
        'reviews_overdue' => '{1} :count risk is overdue for reassessment.|[2,*] :count risks are overdue for reassessment.',
        'action' => 'Open the risk register',
    ],
];
