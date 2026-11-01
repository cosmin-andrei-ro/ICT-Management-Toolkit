<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

it('uses English as the application locale', function (): void {
    expect(config('app.locale'))->toBe('en')
        ->and(__('validation.required', ['attribute' => 'name']))->toBe('The name field is required.');
});
