<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Audit\Domain\Models\Framework;

/**
 * @mixin Framework
 */
final class FrameworkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'has_soa' => $this->has_soa,
            'is_active' => $this->is_active,
            'requirements_count' => $this->whenCounted('requirements'),
        ];
    }
}
