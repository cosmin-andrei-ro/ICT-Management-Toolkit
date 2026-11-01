<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Catalog;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Actions\AddTicketAttachment;
use Modules\Ticketing\Application\Actions\RequestService;
use Modules\Ticketing\Domain\Models\Service;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * @property-read Service $service Computed.
 */
#[Layout('core::components.layouts.app')]
final class RequestServiceForm extends Component
{
    use WithFileUploads;

    /** Same ceiling as free-form tickets: requests per user per hour. */
    private const int MAX_PER_HOUR = 20;

    #[Locked]
    public string $serviceId = '';

    /** @var array<string, mixed> field key => answer */
    public array $answers = [];

    public string $details = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    public function mount(Service $service): void
    {
        $this->authorize('create', Ticket::class);
        abort_unless($service->is_active, 404);

        $this->serviceId = $service->id;
    }

    #[Computed]
    public function service(): Service
    {
        return Service::query()->where('is_active', true)->findOrFail($this->serviceId);
    }

    public function submit(RequestService $requestService, AddTicketAttachment $addAttachment): void
    {
        $this->authorize('create', Ticket::class);
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $this->validate([
            'details' => ['nullable', 'string', 'max:'.RequestService::MAX_DETAILS],
            'files' => ['array', 'max:5'],
            'files.*' => ['file'],
        ]);

        if (RateLimiter::tooManyAttempts('ticketing:create:'.$user->id, self::MAX_PER_HOUR)) {
            $this->addError('details', __('ticketing::ui.portal.too_many'));

            return;
        }

        try {
            $ticket = $requestService->handle($this->service, $this->answers, $this->details, $user);
        } catch (ValidationException $exception) {
            // Errors are keyed by field key; show them next to the matching input.
            /** @var array<string, list<string>> $errors */
            $errors = $exception->errors();

            foreach ($errors as $key => $messages) {
                $this->addError($key === 'details' ? 'details' : "answers.{$key}", $messages[0] ?? '');
            }

            return;
        }

        RateLimiter::hit('ticketing:create:'.$user->id, 3600);

        foreach ($this->files as $file) {
            $addAttachment->handle($ticket, $user, $file, internal: false);
        }

        $this->redirectRoute('ticketing.portal.show', $ticket);
    }

    public function render(): View
    {
        return view('ticketing::livewire.catalog.request', [
            'service' => $this->service,
            'fields' => $this->service->form()->fields,
        ])->title($this->service->name);
    }
}
