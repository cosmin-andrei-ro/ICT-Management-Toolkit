<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'button' => 'Compliance report',
    'title' => 'Compliance report',
    'soa_title' => 'Statement of Applicability - :framework',
    'as_of' => 'As of :date',
    'print' => 'Print',
    'download_pdf' => 'Download PDF',
    'frameworks' => 'Frameworks',
    'requirements' => 'Requirements',
    'met_share' => 'Applicable requirements met',
    'audits' => 'Audits of the last 12 months',
    'open_findings' => 'Findings not closed',
    'tests_overdue' => '{0} No control test overdue.|{1} One control test overdue|[2,*] :count control tests overdue',
    'unmapped' => '{0} Every control meets at least one requirement.|{1} One control meets no requirement|[2,*] :count controls meet no requirement',
    'expired_evidence' => '{0} No expired document.|{1} One document of evidence expired.|[2,*] :count documents of evidence expired.',
    'soa_summary' => ':total requirements: :applicable applicable (or not decided), :excluded excluded, :undecided not decided yet.',
    'included' => 'Applicable',
    'yes' => 'Yes',
    'no' => 'No',
    'undecided' => 'Not decided',
    'export' => [
        'button' => 'Export to spreadsheet',
        'of' => 'Compliance export of :date',
        'applicability' => 'Applicability',
        'last_tested' => 'Last tested',
        'test_overdue' => 'Test overdue',
        'tester' => 'Tested by',
        'evidence' => 'Evidence',
        'kind' => 'Kind',
        'kinds' => [
            'link' => 'Record of the toolkit',
            'file' => 'Document',
        ],
        'added_on' => 'Added on',
        'verified_by' => 'Verified by',
        'closed_on' => 'Closed on',
        'ticket' => 'Ticket',
    ],
    'digest' => [
        'subject' => 'Compliance this week',
        'action' => 'Open compliance',
        'findings_to_verify' => '{1} One finding waits for your verification.|[2,*] :count findings wait for verification.',
        'major_open' => '{1} One major nonconformity is not closed.|[2,*] :count major nonconformities are not closed.',
        'findings_overdue' => '{1} One finding is overdue.|[2,*] :count findings are overdue.',
        'tests_overdue' => '{1} One control test is overdue.|[2,*] :count control tests are overdue.',
        'evidence_expired' => '{1} One document of evidence expired this week.|[2,*] :count documents of evidence expired this week.',
        'requirements_not_covered' => '{1} One applicable requirement has no control.|[2,*] :count applicable requirements have no control.',
    ],
    'risk_signals' => [
        'source' => 'Compliance',
        'major' => [
            'title' => 'Major nonconformity not corrected: :finding',
            'description' => 'The major nonconformity :finding is not closed yet: the organization does not meet a requirement it must meet, with possible sanctions, loss of certification or of customers.',
        ],
        'uncovered' => [
            'title' => 'Requirements of :framework without controls',
            'description' => '{1} One applicable requirement of :framework has no control: the organization may not comply.|[2,*] :count applicable requirements of :framework have no control: the organization may not comply.',
        ],
    ],
];
