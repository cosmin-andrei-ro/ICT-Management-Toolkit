<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Cmdb\Domain\Models\Relationship;

/**
 * "source <type> target", e.g. "ERP depends_on ERP database".
 *
 * @mixin Relationship
 */
final class RelationshipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_id' => $this->source_id,
            'type' => $this->type->value,
            'target_id' => $this->target_id,
            'description' => $this->description,
        ];
    }
}
