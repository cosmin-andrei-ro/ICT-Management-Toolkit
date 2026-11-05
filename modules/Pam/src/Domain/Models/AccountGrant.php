<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * One person may ask for one account's credential.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $account_id
 * @property string $user_id
 * @property-read PrivilegedAccount $account
 * @property-read User $user
 */
#[Table('pam_account_grants')]
#[Fillable(['account_id', 'user_id'])]
final class AccountGrant extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    /**
     * @return BelongsTo<PrivilegedAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PrivilegedAccount::class, 'account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
