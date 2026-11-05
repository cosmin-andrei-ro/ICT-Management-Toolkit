<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Http\Livewire;

use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Pam\Application\Actions\ManageAccessRequests;
use Modules\Pam\Application\VaultSecondFactor;
use Modules\Pam\Domain\Enums\RequestStatus;
use Modules\Pam\Domain\Models\AccessRequest;
use Modules\Pam\Domain\Models\PrivilegedAccount;

/**
 * Requests waiting for this person's decision. A decision needs a fresh code: an
 * approval from a stolen session would hand a credential to whoever asked.
 */
#[Layout('core::components.layouts.app')]
final class RequestInbox extends Component
{
    public string $code = '';

    public string $comment = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PrivilegedAccount::class);
    }

    public function approve(string $requestId, ManageAccessRequests $requests, VaultSecondFactor $secondFactor): void
    {
        $this->decide($requestId, true, $requests, $secondFactor);
    }

    public function reject(string $requestId, ManageAccessRequests $requests, VaultSecondFactor $secondFactor): void
    {
        $this->validate(['comment' => ['required', 'string', 'max:500']]);
        $this->decide($requestId, false, $requests, $secondFactor);
    }

    public function render(): View
    {
        $user = $this->actor();
        $this->authorize('viewAny', PrivilegedAccount::class);

        return view('pam::livewire.requests', [
            'requests' => AccessRequest::query()->with(['account', 'user'])
                ->where('status', RequestStatus::Pending)
                ->orderBy('created_at')
                ->get()
                ->filter(fn (AccessRequest $request): bool => $user->can('decide', $request)),
        ])->title(__('pam::accounts.requests.title'));
    }

    private function decide(string $requestId, bool $approved, ManageAccessRequests $requests, VaultSecondFactor $secondFactor): void
    {
        $request = AccessRequest::query()->with(['account', 'user'])->findOrFail($requestId);
        $this->authorize('decide', $request);
        $this->validate(['code' => ['required', 'string', 'max:32'], 'comment' => ['nullable', 'string', 'max:500']]);
        $code = $this->code;
        $this->reset('code');
        $secondFactor->confirm($this->actor(), $code, 'pam_decide_request', 'code');

        try {
            $requests->decide($request, $this->actor(), $approved, $this->comment);
        } catch (InvalidArgumentException) {
            abort(409);
        }

        $this->reset('comment');
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
