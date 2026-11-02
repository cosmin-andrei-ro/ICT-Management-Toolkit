<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Rack;

/**
 * @extends Factory<Mount>
 */
final class MountFactory extends Factory
{
    protected $model = Mount::class;

    public function definition(): array
    {
        return [
            'rack_id' => Rack::factory(),
            'asset_id' => (string) Str::uuid7(),
            'position_u' => 1,
            'height_u' => 1,
            'face' => 'front',
            'full_depth' => true,
        ];
    }
}
