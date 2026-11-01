<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

it('responds on the health check endpoint', function (): void {
    $this->get('/up')->assertOk();
});
