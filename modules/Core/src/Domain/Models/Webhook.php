<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\WebhookFactory;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;

/**
 * An endpoint the organization's events are sent to (ADR 0066).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $url
 * @property string $secret Encrypted at rest, never logged.
 * @property list<string> $events
 * @property bool $is_active
 * @property ?string $created_by
 * @property ?string $updated_by
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
#[Table('core_webhooks')]
#[Fillable(['name', 'url', 'events', 'is_active'])]
#[UseFactory(WebhookFactory::class)]
final class Webhook extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<WebhookFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    /** @var list<string> */
    protected $hidden = ['secret'];

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest();
    }

    public function listensTo(string $event): bool
    {
        return in_array($event, $this->events, true);
    }

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
