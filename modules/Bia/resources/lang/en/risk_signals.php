<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'source' => 'Business impact analysis',
    'gap' => [
        'title' => ':item cannot be recovered as fast as the business needs',
        'description' => 'The business needs :item back within :rto (:processes), more than IT declared it can deliver, or no recovery time is declared that fits.',
    ],
    'supplier' => [
        'title' => 'Failure of :supplier stops :process',
        'description' => 'The critical process :process depends on the supplier :supplier.',
    ],
];
