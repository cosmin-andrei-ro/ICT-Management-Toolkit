<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * One event for one webhook, and how its delivery went.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $webhook_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property string $status pending, delivered or failed
 * @property int $attempts
 * @property ?int $response_status
 * @property ?string $error
 * @property ?Carbon $next_attempt_at
 * @property ?Carbon $delivered_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Webhook $webhook
 */
#[Table('core_webhook_deliveries')]
final class WebhookDelivery extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    /**
     * @return BelongsTo<Webhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'response_status' => 'integer',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
