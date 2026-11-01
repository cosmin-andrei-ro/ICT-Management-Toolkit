<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Policies\ExportPolicy;
use Modules\Core\Database\Factories\ExportFactory;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportStatus;

/**
 * A file generated for one person. Written only by RequestExport and
 * GenerateExport (no mass assignment); requests and downloads are audited.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string $type
 * @property ExportFormat $format
 * @property array<string, mixed> $parameters
 * @property ExportStatus $status
 * @property ?string $disk
 * @property ?string $path
 * @property ?string $filename
 * @property ?int $row_count
 * @property ?Carbon $completed_at
 * @property ?Carbon $expires_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read User $user
 */
#[Table('core_exports')]
#[UseFactory(ExportFactory::class)]
#[UsePolicy(ExportPolicy::class)]
final class Export extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ExportFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportStatus::Ready && $this->expires_at?->isFuture() === true;
    }

    protected function casts(): array
    {
        return [
            'format' => ExportFormat::class,
            'parameters' => 'array',
            'status' => ExportStatus::class,
            'row_count' => 'integer',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
