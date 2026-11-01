<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Actions\AddTicketAttachment;
use Modules\Ticketing\Application\Actions\CreateTicket as CreateTicketAction;
use Modules\Ticketing\Application\Actions\NewTicketData;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Domain\Models\Ticket;

#[Layout('core::components.layouts.app')]
final class CreateTicket extends Component
{
    use WithFileUploads;

    /** Tickets per user per hour: a runaway script should not flood the queue. */
    private const int MAX_PER_HOUR = 20;

    private const int MAX_FILES = 5;

    public string $type = 'incident';

    public string $title = '';

    public string $description = '';

    public ?string $categoryId = null;

    public string $impact = 'low';

    public string $urgency = 'low';

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    public function mount(): void
    {
        $this->authorize('create', Ticket::class);
    }

    public function save(CreateTicketAction $createTicket, AddTicketAttachment $addAttachment): void
    {
        $this->authorize('create', Ticket::class);
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $this->validate();

        if (RateLimiter::tooManyAttempts('ticketing:create:'.$user->id, self::MAX_PER_HOUR)) {
            $this->addError('title', __('ticketing::ui.portal.too_many'));

            return;
        }

        RateLimiter::hit('ticketing:create:'.$user->id, 3600);

        $ticket = $createTicket->handle(new NewTicketData(
            type: TicketType::from($this->type),
            title: $this->title,
            description: $this->description,
            impact: Impact::from($this->impact),
            urgency: Urgency::from($this->urgency),
            channel: TicketChannel::Portal,
            categoryId: $this->categoryId,
        ), $user, $user);

        foreach ($this->files as $file) {
            $addAttachment->handle($ticket, $user, $file, internal: false);
        }

        $this->redirectRoute('ticketing.portal.show', $ticket);
    }

    public function render(): View
    {
        return view('ticketing::livewire.portal.create', [
            'categories' => Category::query()->where('is_active', true)->with('parent')->orderBy('sort_order')->orderBy('name')->get(),
            'types' => TicketType::cases(),
            'impacts' => Impact::cases(),
            'urgencies' => Urgency::cases(),
        ])->title(__('ticketing::ui.portal.new'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TicketType::class)],
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'description' => ['required', 'string', 'max:20000'],
            // Restricted to the current organization: another organization's category does not exist here.
            'categoryId' => ['nullable', 'uuid', Rule::exists('ticketing_categories', 'id')->where('is_active', true)->where('organization_id', app(OrganizationContext::class)->id())],
            'impact' => ['required', Rule::enum(Impact::class)],
            'urgency' => ['required', Rule::enum(Urgency::class)],
            'files' => ['array', 'max:'.self::MAX_FILES],
            'files.*' => ['file'],
        ];
    }
}
