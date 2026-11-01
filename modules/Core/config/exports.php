<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [

    // Days a generated export can be downloaded before it is deleted.
    'retention_days' => (int) env('EXPORTS_RETENTION_DAYS', 7),

];
