<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Cmdb\Domain\Models\CiType;

/**
 * A configuration item type and the attributes its items carry in `properties`.
 *
 * @mixin CiType
 */
final class CiTypeResource extends JsonResource
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
            'description' => $this->description,
            'is_active' => $this->is_active,
            /** Key, label, type, required, help and options of each attribute. */
            'attributes' => $this->attribute_schema,
        ];
    }
}
