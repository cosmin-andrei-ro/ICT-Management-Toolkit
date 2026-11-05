<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Who was shown which credential, when and why, from the append-only audit trail.
 */
#[Layout('core::components.layouts.app')]
final class AccessLog extends Component
{
    use WithPagination;

    public function render(OrganizationContext $context): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->can('pam.audit.view'), 403);

        return view('pam::livewire.log', [
            // The audit log has no organization scope of its own.
            'entries' => AuditLog::query()->with(['user:id,name', 'auditable' => fn (MorphTo $account) => $account->withTrashed()])
                ->where('organization_id', $context->requireId())
                ->where('auditable_type', 'pam.account')
                ->where('event', AuditEvent::SecretRevealed->value)
                ->orderByDesc('created_at')
                ->paginate(50),
            'timezone' => config()->string('app.display_timezone'),
        ])->title(__('pam::accounts.log.title'));
    }
}
