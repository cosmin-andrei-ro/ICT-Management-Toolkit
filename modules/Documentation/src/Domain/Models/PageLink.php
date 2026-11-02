<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A record of another module a page is about (an application, a CMDB item,
 * a plan…), by morph type and id, with its title kept for readers who may
 * not open it.
 *
 * @property string $id
 * @property string $page_id
 * @property string $linked_type
 * @property string $linked_id
 * @property string $title
 * @property ?string $created_by
 * @property Carbon $created_at
 */
#[Table('documentation_page_links')]
final class PageLink extends Model
{
    use HasUuids;

    public const ?string UPDATED_AT = null;
}
