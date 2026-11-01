<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [

    // Days a resolved ticket waits for the requester before closing on its own.
    // The requester can reopen it until then. 0 disables automatic closing.
    'auto_close_after_days' => (int) env('TICKETING_AUTO_CLOSE_AFTER_DAYS', 5),

];
