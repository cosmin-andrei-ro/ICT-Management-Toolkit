<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Application\Collaboration\AddComment;
use Modules\Core\Application\Collaboration\StoreAttachment;
use Modules\Core\Application\Retention\ApplyRetention;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\Comment;
use Modules\Core\Domain\Models\RetentionSettings;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('attachments');
    $this->desk = TicketingScenario::create();
});

function closedTicket(TicketingScenario $desk, string $title, int $yearsAgo, string $description = 'My home address is 1 Example Street.'): Ticket
{
    $ticket = $desk->ticket(title: $title);
    $closed = now()->subYears($yearsAgo)->subDay();
    $ticket->forceFill(['status' => TicketStatus::Closed, 'resolved_at' => $closed, 'resolution_code' => ResolutionCode::cases()[0], 'closed_at' => $closed, 'description' => $description])->save();

    return $ticket;
}

it('anonymizes closed tickets past the period, here and in the audit log, and keeps what statistics need', function (): void {
    $old = closedTicket($this->desk, 'Salary slip of Ana missing', 4);
    $old->watchers()->attach($this->desk->agent->id);
    app(AddComment::class)->handle($old, $this->desk->requester, 'Please call me on 0700 000 000.', false);
    $file = app(StoreAttachment::class)->handle($old, $this->desk->requester, UploadedFile::fake()->createWithContent('scan.txt', 'personal'), false);
    $recent = closedTicket($this->desk, 'Recent closed ticket', 1, 'Nothing personal in this one.');
    $open = $this->desk->ticket(title: 'Old but still open');
    $open->forceFill(['created_at' => now()->subYears(5)])->save();

    $report = app(ApplyRetention::class)->handle();

    $old->refresh();
    expect($report[$this->desk->organization->id]['ticketing.closed_tickets'])->toBe(1)
        ->and($old->title)->toBe('Anonymized ticket')
        ->and($old->description)->toBe('')
        ->and($old->requester_id)->toBeNull()
        ->and($old->anonymized_at)->not->toBeNull()
        ->and($old->number)->not->toBeEmpty()
        ->and($old->watchers()->exists())->toBeFalse()
        ->and(Comment::query()->withTrashed()->where('commentable_id', $old->id)->exists())->toBeFalse()
        ->and(Attachment::query()->withTrashed()->whereKey($file->id)->exists())->toBeFalse();
    Storage::disk('attachments')->assertMissing($file->path);

    $logged = AuditLog::query()->get()->map(fn (AuditLog $entry): string => json_encode([$entry->old_values, $entry->new_values]))->implode(' ');
    expect($logged)->not->toContain('Salary slip of Ana')->not->toContain('1 Example Street')->not->toContain('0700 000 000')->not->toContain('scan.txt')
        ->and($recent->fresh()->title)->toBe('Recent closed ticket')
        ->and($open->fresh()->title)->toBe('Old but still open');
});

it('follows the organization\'s period, and keeps forever when it has none', function (): void {
    $ticket = closedTicket($this->desk, 'Closed four years ago', 4);

    (new RetentionSettings)->forceFill(['periods' => ['ticketing.closed_tickets' => 5]])->save();
    app(ApplyRetention::class)->handle();
    expect($ticket->fresh()->anonymized_at)->toBeNull();

    RetentionSettings::current()->forceFill(['periods' => ['ticketing.closed_tickets' => null]])->save();
    app(ApplyRetention::class)->handle(now()->addYears(20)->toImmutable());
    expect($ticket->fresh()->anonymized_at)->toBeNull();
});

it('shows an anonymized ticket to IT support without a requester', function (): void {
    $ticket = closedTicket($this->desk, 'To be forgotten', 4);
    app(ApplyRetention::class)->handle();

    $this->actingAs($this->desk->agent)->get(route('ticketing.desk.show', $ticket))->assertOk()->assertSee('Anonymized ticket')->assertSee('Anonymized');
    $this->actingAs($this->desk->agent)->get(route('ticketing.desk.index'))->assertOk();
});
