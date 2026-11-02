<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'source' => 'Application portfolio',
    'data' => [
        'title' => 'Breach of the personal data processed by :application',
        'description' => 'The documentation of :application says it processes personal data (GDPR). A breach would have to be notified and could be fined.',
        'sensitive' => 'The documentation of :application says it processes special-category personal data, health records or payment card data: a breach would be serious, notifiable and heavily fined.',
    ],
    'no_mfa' => [
        'title' => 'Unauthorized access to :application from the internet',
        'description' => ':application is reachable from the internet and its documentation does not show multifactor authentication: stolen or guessed passwords are enough to get in.',
    ],
    'certificates' => [
        'title' => 'Interruption of :application when its certificates expire',
        'description' => 'Certificates of :application expire within 30 days or have expired: :certificates.',
    ],
    'unsupported' => [
        'title' => 'Unpatched weaknesses in :application\'s components',
        'description' => 'Components of :application are past their end of support and receive no security fixes: :components.',
    ],
];
