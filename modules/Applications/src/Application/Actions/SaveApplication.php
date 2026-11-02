<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Actions;

use Modules\Applications\Domain\Models\Application;

/**
 * The application's identity and owners (the template's cover page). Audited
 * by the application's own trail.
 */
final readonly class SaveApplication
{
    /**
     * @param  array{code: string, name: string, department_id: ?string, department_email: ?string, business_owner_id: ?string, technical_owner_id: ?string, deputy_id: ?string}  $data  Validated.
     */
    public function handle(?Application $application, array $data): Application
    {
        $application ??= new Application;
        $application->fill([
            ...$data,
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
        ])->save();

        return $application;
    }
}
