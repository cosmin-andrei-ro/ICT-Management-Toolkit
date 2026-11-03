<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * The organization's security settings (ADR 0057): which of its roles must
 * use two-factor authentication. Never set: every role but Requester, as
 * before the setting existed. Administrators always must.
 *
 * @property string $organization_id
 * @property ?list<string> $mfa_required_roles
 * @property ?string $updated_by
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
#[Table('core_security_settings')]
final class SecuritySettings extends Model
{
    use Auditable;
    use BelongsToOrganization;

    protected $primaryKey = 'organization_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The current organization's settings, or the defaults when never changed.
     */
    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    /**
     * Whether holding this role means having to use two-factor authentication.
     */
    public function requiresMfa(string $role): bool
    {
        if ($role === DefaultRole::Administrator->value) {
            return true;
        }

        // Never set: any role but Requester, the organization's own roles included.
        return $this->mfa_required_roles === null
            ? $role !== DefaultRole::Requester->value
            : in_array($role, $this->mfa_required_roles, true);
    }

    protected function casts(): array
    {
        return [
            'mfa_required_roles' => 'array',
        ];
    }
}
