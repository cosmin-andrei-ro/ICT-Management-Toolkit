<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\Comment;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Domain\Enums\Priority;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\MonitoringAlert;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Http\Livewire\Desk\ShowTicket;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->desk = TicketingScenario::create();
    $this->monitor = $this->desk->member(DefaultRole::Agent);
    $this->server = ConfigurationItem::factory()->create(['name' => 'Payroll database server', 'identifier' => 'srv-db-01', 'criticality' => 'high']);
});

/**
 * @param  list<string>  $abilities
 * @return array<string, string>
 */
function monitoringHeaders(User $user, array $abilities = ['monitoring:write']): array
{
    $token = app(IssueApiToken::class)->handle($user, 'monitoring', $abilities);
    auth()->forgetGuards();

    return ['Authorization' => 'Bearer '.$token->plainTextToken, 'Accept' => 'application/json'];
}

function firing(array $overrides = []): array
{
    return ['key' => 'srv-db-01:disk', 'status' => 'firing', 'title' => 'Disk almost full on srv-db-01', 'severity' => 'critical', 'item' => 'SRV-DB-01', 'source' => 'MonitorTool', ...$overrides];
}

it('opens an incident linked to the item the alert names, and counts repeats on it', function (): void {
    $headers = monitoringHeaders($this->monitor);

    $created = $this->postJson('/api/v1/monitoring/alerts', firing(['description' => '/var at 95%']), $headers)
        ->assertStatus(202)->assertJsonPath('data.action', 'created');

    $ticket = Ticket::query()->sole();
    expect($ticket)
        ->channel->toBe(TicketChannel::Monitoring)
        ->priority->toBe(Priority::Critical)
        ->requester_id->toBe($this->monitor->id)
        ->and($ticket->description)->toContain('/var at 95%')->toContain('MonitorTool')->toContain('Payroll database server')
        ->and(array_map(fn ($item): string => $item->id, app(ConfigurationItems::class)->linkedTo($ticket)))->toBe([$this->server->id])
        ->and($created->json('data.alert.ticket.number'))->toBe($ticket->number);

    $this->postJson('/api/v1/monitoring/alerts', firing(), $headers)->assertStatus(202)->assertJsonPath('data.action', 'repeated')->assertJsonPath('data.alert.occurrences', 2);
    $this->postJson('/api/v1/monitoring/alerts', firing(['key' => 'srv-db-01:cpu', 'title' => 'CPU high', 'severity' => 'info']), $headers)->assertJsonPath('data.action', 'created');

    expect(Ticket::query()->count())->toBe(2)
        ->and(Ticket::query()->where('title', 'CPU high')->sole()->priority)->toBe(Priority::Low);
});

it('resolves the incident when the alert clears, if nobody started work on it', function (): void {
    $headers = monitoringHeaders($this->monitor);
    $this->postJson('/api/v1/monitoring/alerts', firing(), $headers);
    $this->postJson('/api/v1/monitoring/alerts', firing(), $headers);

    $this->postJson('/api/v1/monitoring/alerts', ['key' => 'srv-db-01:disk', 'status' => 'resolved'], $headers)->assertJsonPath('data.action', 'resolved');

    $ticket = Ticket::query()->sole();
    expect($ticket->status)->toBe(TicketStatus::Resolved)->and($ticket->resolution_code)->toBe(ResolutionCode::NoActionNeeded)
        ->and($ticket->resolution_note)->toContain('2 occurrence')
        ->and(MonitoringAlert::query()->sole())->status->toBe('resolved')->resolved_at->not->toBeNull();

    // Firing again opens a new incident; clearing an unknown key does nothing.
    $this->postJson('/api/v1/monitoring/alerts', firing(), $headers)->assertJsonPath('data.action', 'created');
    $this->postJson('/api/v1/monitoring/alerts', ['key' => 'never-fired', 'status' => 'resolved'], $headers)->assertJsonPath('data.action', 'ignored')->assertJsonPath('data.alert', null);
    expect(Ticket::query()->count())->toBe(2);
});

it('only leaves a note when an agent is working on the incident', function (): void {
    $headers = monitoringHeaders($this->monitor);
    $this->postJson('/api/v1/monitoring/alerts', firing(), $headers);
    $ticket = Ticket::query()->sole();
    $ticket->forceFill(['status' => TicketStatus::InProgress, 'assignee_id' => $this->desk->agent->id])->save();

    $this->postJson('/api/v1/monitoring/alerts', ['key' => 'srv-db-01:disk', 'status' => 'resolved'], monitoringHeaders($this->monitor))->assertJsonPath('data.action', 'noted');

    expect($ticket->fresh()?->status)->toBe(TicketStatus::InProgress)
        ->and(Comment::query()->where('commentable_id', $ticket->id)->sole())->is_internal->toBeTrue()->body->toContain('cleared');
});

it('opens a new incident when the previous one was closed while the alert kept firing', function (): void {
    $headers = monitoringHeaders($this->monitor);
    $this->postJson('/api/v1/monitoring/alerts', firing(), $headers);
    Ticket::query()->sole()->forceFill(['status' => TicketStatus::Cancelled])->save();

    $this->postJson('/api/v1/monitoring/alerts', firing(), monitoringHeaders($this->monitor))->assertJsonPath('data.action', 'created');

    expect(Ticket::query()->count())->toBe(2)->and(MonitoringAlert::query()->where('status', 'firing')->count())->toBe(1);
});

it('opens the incident without an item when the name matches none, or several', function (): void {
    ConfigurationItem::factory()->create(['name' => 'web', 'identifier' => null]);
    ConfigurationItem::factory()->create(['name' => 'WEB', 'identifier' => null]);
    $headers = monitoringHeaders($this->monitor);

    $this->postJson('/api/v1/monitoring/alerts', firing(['key' => 'a', 'item' => 'unknown-host']), $headers)->assertJsonPath('data.action', 'created');
    $this->postJson('/api/v1/monitoring/alerts', firing(['key' => 'b', 'item' => 'web']), $headers)->assertJsonPath('data.action', 'created');
    $this->postJson('/api/v1/monitoring/alerts', firing(['key' => 'c', 'item' => null, 'item_id' => $this->server->id]), $headers)->assertJsonPath('data.action', 'created');

    expect(MonitoringAlert::query()->orderBy('key')->pluck('configuration_item_id')->all())->toBe([null, null, $this->server->id])
        ->and(Ticket::query()->where('description', 'like', '%unknown-host%')->exists())->toBeTrue();
});

it('understands Prometheus Alertmanager notifications', function (): void {
    $headers = monitoringHeaders($this->monitor);
    $payload = fn (string $status): array => ['version' => '4', 'status' => $status, 'receiver' => 'toolkit', 'alerts' => [
        ['status' => $status, 'fingerprint' => 'c0ffee', 'labels' => ['alertname' => 'DiskFull', 'instance' => 'srv-db-01:9100', 'severity' => 'warning'], 'annotations' => ['summary' => 'Disk almost full', 'description' => '/var at 95%']],
    ]];

    $this->postJson('/api/v1/monitoring/alertmanager', $payload('firing'), $headers)->assertStatus(202)->assertJsonPath('data.0.action', 'created');
    $ticket = Ticket::query()->sole();
    expect($ticket->title)->toBe('Disk almost full')->and(MonitoringAlert::query()->sole())->key->toBe('alertmanager:c0ffee')->configuration_item_id->toBe($this->server->id)->source->toBe('Alertmanager');

    $this->postJson('/api/v1/monitoring/alertmanager', $payload('resolved'), $headers)->assertJsonPath('data.0.action', 'resolved');
    expect($ticket->fresh()?->status)->toBe(TicketStatus::Resolved);
});

it('validates alerts', function (): void {
    $headers = monitoringHeaders($this->monitor);

    $this->postJson('/api/v1/monitoring/alerts', ['key' => 'x', 'status' => 'firing'], $headers)->assertUnprocessable()->assertJsonValidationErrors('title');
    $this->postJson('/api/v1/monitoring/alerts', firing(['status' => 'flapping', 'severity' => 'apocalyptic', 'item_id' => 'not-a-uuid']), $headers)->assertUnprocessable()->assertJsonValidationErrors(['status', 'severity', 'item_id']);
    $this->postJson('/api/v1/monitoring/alertmanager', ['alerts' => []], $headers)->assertUnprocessable()->assertJsonValidationErrors('alerts');
    expect(Ticket::query()->count())->toBe(0);
});

it('needs the monitoring ability and an agent', function (): void {
    $this->postJson('/api/v1/monitoring/alerts', firing(), monitoringHeaders($this->monitor, ['tickets:write']))->assertForbidden();
    $this->postJson('/api/v1/monitoring/alerts', firing(), monitoringHeaders($this->desk->requester))->assertForbidden();
    $this->postJson('/api/v1/monitoring/alertmanager', ['alerts' => [['status' => 'firing', 'labels' => ['alertname' => 'X']]]], monitoringHeaders($this->desk->requester))->assertForbidden();
    auth()->forgetGuards();
    $this->postJson('/api/v1/monitoring/alerts', firing(), ['Accept' => 'application/json'])->assertUnauthorized();
    expect(Ticket::query()->count())->toBe(0);
});

it('keeps alerts of one organization from another', function (): void {
    $this->postJson('/api/v1/monitoring/alerts', firing(), monitoringHeaders($this->monitor));
    $other = TicketingScenario::create();
    $stranger = $other->member(DefaultRole::Agent);

    // Same key, other organization: its own incident, and our item is not linked.
    $this->postJson('/api/v1/monitoring/alerts', firing(), monitoringHeaders($stranger))->assertJsonPath('data.action', 'created');

    app(OrganizationContext::class)->set($other->organization);
    expect(MonitoringAlert::query()->sole()->configuration_item_id)->toBeNull();
});

it('shows the alert on the incident for agents', function (): void {
    $this->postJson('/api/v1/monitoring/alerts', firing(), monitoringHeaders($this->monitor));
    $this->postJson('/api/v1/monitoring/alerts', firing(), monitoringHeaders($this->monitor));
    $this->actingAs($this->desk->agent);

    Livewire::test(ShowTicket::class, ['ticket' => Ticket::query()->sole()])
        ->assertSee('Monitoring alert')->assertSee('srv-db-01:disk')->assertSee('MonitorTool')->assertSee('Firing');
});
