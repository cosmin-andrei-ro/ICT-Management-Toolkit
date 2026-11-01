<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Forms\FormSchema;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Policies\TicketingSettingsPolicy;
use Modules\Ticketing\Database\Factories\ServiceFactory;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Urgency;

/**
 * An entry of the service catalog.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property ?string $description
 * @property ?string $category_id
 * @property ?string $queue_id
 * @property Impact $impact
 * @property Urgency $urgency
 * @property list<array<string, mixed>> $form_schema
 * @property ?string $owner_id
 * @property bool $is_active
 * @property int $sort_order
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?Category $category
 * @property-read ?Queue $queue
 * @property-read ?User $owner
 */
#[Table('ticketing_services')]
#[UseFactory(ServiceFactory::class)]
#[UsePolicy(TicketingSettingsPolicy::class)]
#[Fillable(['name', 'description', 'category_id', 'queue_id', 'impact', 'urgency', 'form_schema', 'owner_id', 'is_active', 'sort_order'])]
final class Service extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    protected static function booted(): void
    {
        // An invalid form must never be stored. Returns nothing: "saving" halts on a value.
        self::saving(function (self $service): void {
            FormSchema::fromArray($service->form_schema);
        });
    }

    public function form(): FormSchema
    {
        return FormSchema::fromArray($this->form_schema);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Queue, $this>
     */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    protected function casts(): array
    {
        return [
            'impact' => Impact::class,
            'urgency' => Urgency::class,
            'form_schema' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
