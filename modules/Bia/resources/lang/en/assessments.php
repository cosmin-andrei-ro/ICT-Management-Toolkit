<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'statuses' => [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'returned' => 'Returned',
        'approved' => 'Approved',
        'superseded' => 'Superseded',
    ],
    'ui' => [
        'title' => 'Assessment v:version',
        'back' => '← :process',
        'matrix' => 'Impact of an interruption over time',
        'matrix_help' => 'For each kind of impact, how bad would it be if the process stopped for this long? 1 = negligible, 5 = severe. Hover over a level to see what it means. Impact never goes down with time. Save to see the MTPD and the cells at the unacceptable level.',
        'unacceptable' => 'From level :level, the impact is unacceptable.',
        'category' => 'Impact',
        'mtpd' => 'Maximum tolerable period of disruption (MTPD)',
        'mtpd_computed' => 'From the scores: :value.',
        'mtpd_none' => 'From the scores: longer than the last period scored.',
        'mtpd_refresh' => 'Updated when you save.',
        'override' => 'Use another MTPD',
        'override_reason' => 'Why',
        'objectives' => 'Recovery objectives',
        'rto' => 'Recovery time objective (RTO)',
        'rto_help' => 'How fast the process must work again. At most the MTPD.',
        'rpo' => 'Recovery point objective (RPO)',
        'rpo_help' => 'How much recent data may be lost: 0 for none, 1 day if yesterday\'s backup is enough.',
        'mbco' => 'Minimum level of service while recovering (MBCO)',
        'workarounds' => 'Manual workarounds',
        'workarounds_help' => 'How the work can go on, for a while, without IT: paper forms, phone, another site.',
        'minimum_staff' => 'Minimum staff',
        'notes' => 'Notes',
        'save' => 'Save draft',
        'submit' => 'Submit for approval',
        'decision' => 'Decision',
        'comment' => 'Comment',
        'comment_help' => 'Required to return the assessment.',
        'approve' => 'Approve',
        'send_back' => 'Return to the owner',
        'submitted_by' => 'Submitted by :name on :date.',
        'decided_by' => ':status by :name on :date.',
        'read_only' => 'This version can no longer change.',
        'waiting' => 'Waiting for the BIA coordinator.',
    ],
    'errors' => [
        'incomplete' => 'Score every kind of impact at every period before submitting.',
        'decreasing' => 'Impact goes down with time for: :categories. Check the scores.',
        'rto_required' => 'Set the RTO.',
        'rpo_required' => 'Set the RPO.',
        'rto_over_mtpd' => 'The RTO cannot be longer than the MTPD.',
        'mtpd_reason' => 'Explain why the MTPD differs from the one the scores give.',
        'return_reason' => 'Say what to change.',
        'locked' => 'This assessment was submitted or decided meanwhile; reload the page.',
        'cannot_decide' => 'You cannot decide on this assessment: it is no longer waiting, or you own the process or submitted it.',
    ],
    'mail' => [
        'submitted' => [
            'subject' => 'BIA to approve: :process (v:version)',
            'line' => 'The impact analysis of ":process" (version :version) was submitted for approval.',
        ],
        'approved' => [
            'subject' => 'BIA approved: :process (v:version)',
            'line' => 'The impact analysis of ":process" (version :version) was approved. Its recovery objectives are now in force.',
        ],
        'returned' => [
            'subject' => 'BIA returned: :process (v:version)',
            'line' => 'The impact analysis of ":process" (version :version) was returned to you for changes.',
        ],
        'comment' => 'Comment: :comment',
        'open' => 'Open the assessment',
    ],
];
