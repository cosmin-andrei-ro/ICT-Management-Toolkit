<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Audit\Domain\Models\Audit;

/**
 * @mixin Audit
 */
final class AuditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->code(),
            'title' => $this->title,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'scope' => $this->scope,
            'lead_auditor_id' => $this->lead_auditor_id,
            'audit_body' => $this->audit_body,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'conclusion' => $this->conclusion,
        ];
    }
}
