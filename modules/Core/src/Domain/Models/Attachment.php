<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Policies\AttachmentPolicy;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * Metadata of a stored file. Created only through StoreAttachment; downloaded only
 * through AttachmentController after the parent's policy allows it.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $attachable_type
 * @property string $attachable_id
 * @property ?string $uploaded_by
 * @property bool $is_internal
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $checksum_sha256
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?User $uploader
 */
#[Table('core_attachments')]
#[UsePolicy(AttachmentPolicy::class)]
final class Attachment extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use SoftDeletes;

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'size' => 'integer',
        ];
    }
}
