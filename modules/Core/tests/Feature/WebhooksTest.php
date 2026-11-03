<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Application\Sso\HostResolver;
use Modules\Core\Application\Webhooks\DeliverWebhook;
use Modules\Core\Application\Webhooks\PublishWebhookEvent;
use Modules\Core\Application\Webhooks\WebhookUrlGuard;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Models\Webhook;
use Modules\Core\Domain\Models\WebhookDelivery;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Webhooks\WebhookEvents;
use Modules\Core\Http\Filament\Resources\WebhookDeliveries\Pages\ListWebhookDeliveries;
use Modules\Core\Http\Filament\Resources\Webhooks\Pages\ManageWebhooks;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    app(WebhookEvents::class)->register('thing.happened', 'core::webhooks.title');
    webhookResolvesTo('203.0.113.10');
});

function webhookResolvesTo(string ...$addresses): void
{
    app()->instance(HostResolver::class, new readonly class(array_values($addresses)) implements HostResolver
    {
        /** @param list<string> $addresses */
        public function __construct(private array $addresses) {}

        public function resolve(string $host): array
        {
            return $this->addresses;
        }
    });
}

function webhookAdmin(Organization $organization): User
{
    $admin = User::factory()->memberOf($organization)->withTwoFactor()->create();
    $admin->assignRole(DefaultRole::Administrator->value);

    return $admin;
}

it('sends a signed delivery carrying only the essentials and the API link', function (): void {
    Http::fake(['hooks.example.com/*' => Http::response('', 204)]);
    $webhook = Webhook::factory()->create(['url' => 'https://hooks.example.com/in', 'events' => ['thing.happened'], 'secret' => 'whsec_test']);

    expect(app(PublishWebhookEvent::class)->handle('thing.happened', ['id' => 'abc', 'reference' => 'INC-000001', 'title' => 'Printer', 'url' => 'https://toolkit.example/api/v1/tickets/abc']))->toBe(1);

    $delivery = WebhookDelivery::query()->sole();
    expect($delivery->status)->toBe('delivered')->and($delivery->response_status)->toBe(204)->and($delivery->attempts)->toBe(1);

    [$request] = Http::recorded()->sole();
    /** @var Request $request */
    preg_match('/^t=(\d+),v1=([0-9a-f]{64})$/', $request->header('X-Webhook-Signature')[0], $signature);
    expect($request->url())->toBe('https://hooks.example.com/in')
        ->and($request->header('X-Webhook-Event'))->toBe(['thing.happened'])
        ->and($request->header('X-Webhook-Delivery'))->toBe([$delivery->id])
        ->and($signature[2] ?? null)->toBe(hash_hmac('sha256', ($signature[1] ?? '').'.'.$request->body(), 'whsec_test'))
        ->and($request['data'])->toEqual(['id' => 'abc', 'reference' => 'INC-000001', 'title' => 'Printer', 'url' => 'https://toolkit.example/api/v1/tickets/abc'])
        ->and(array_keys($request->data()))->toEqualCanonicalizing(['id', 'event', 'occurred_at', 'organization_id', 'data']);
    expect($webhook->getAttributes()['secret'])->not->toContain('whsec_test');
});

it('sends only to active webhooks of the organization that subscribe to the event', function (): void {
    Http::fake();
    Webhook::factory()->create(['name' => 'Subscribed', 'events' => ['thing.happened']]);
    Webhook::factory()->create(['name' => 'Other event', 'events' => ['ticket.created']]);
    Webhook::factory()->create(['name' => 'Paused', 'events' => ['thing.happened'], 'is_active' => false]);
    $other = Organization::factory()->create();
    app(OrganizationContext::class)->runAs($other, fn () => Webhook::factory()->create(['name' => 'Theirs', 'events' => ['thing.happened']]));

    app(PublishWebhookEvent::class)->handle('thing.happened', ['id' => 'abc']);

    expect(WebhookDelivery::query()->with('webhook')->get()->pluck('webhook.name')->all())->toBe(['Subscribed']);
    expect(fn () => app(PublishWebhookEvent::class)->handle('nothing.registered', ['id' => 'abc']))->toThrow(InvalidArgumentException::class);
});

it('publishes for the organization it is told, outside any request', function (): void {
    Http::fake();
    $webhook = Webhook::factory()->create(['events' => ['thing.happened']]);
    app(OrganizationContext::class)->clear();

    expect(app(PublishWebhookEvent::class)->handle('thing.happened', ['id' => 'abc'], $this->organization->id))->toBe(1)
        ->and(WebhookDelivery::query()->withoutGlobalScopes()->sole()->webhook_id)->toBe($webhook->id);
});

it('sends nothing when the change that raised the event is rolled back', function (): void {
    Http::fake();
    Webhook::factory()->create(['events' => ['thing.happened']]);

    DB::beginTransaction();
    app(PublishWebhookEvent::class)->handle('thing.happened', ['id' => 'abc']);
    DB::rollBack();

    Http::assertNothingSent();
    expect(WebhookDelivery::query()->count())->toBe(0);
});

it('tries again later when the receiver fails, then gives up', function (): void {
    $this->freezeSecond();
    Queue::fake();
    Http::fake(['*' => Http::response('down', 503)]);
    Webhook::factory()->create(['events' => ['thing.happened']]);
    app(PublishWebhookEvent::class)->handle('thing.happened', ['id' => 'abc']);
    $delivery = WebhookDelivery::query()->sole();

    foreach (DeliverWebhook::BACKOFF as $delay) {
        app()->call([new DeliverWebhook($delivery->id), 'handle']);
        $delivery->refresh();
        expect($delivery->status)->toBe('pending')->and($delivery->response_status)->toBe(503)
            ->and((int) round(now()->diffInSeconds($delivery->next_attempt_at)))->toBe($delay);
    }
    app()->call([new DeliverWebhook($delivery->id), 'handle']);

    expect($delivery->refresh()->status)->toBe('failed')->and($delivery->attempts)->toBe(6)->and($delivery->next_attempt_at)->toBeNull();
    Queue::assertPushed(DeliverWebhook::class, 1 + count(DeliverWebhook::BACKOFF));
});

it('stops retrying once the webhook is paused', function (): void {
    Queue::fake();
    Http::fake();
    $webhook = Webhook::factory()->create(['events' => ['thing.happened']]);
    app(PublishWebhookEvent::class)->handle('thing.happened', ['id' => 'abc']);
    $webhook->update(['is_active' => false]);

    app()->call([new DeliverWebhook(WebhookDelivery::query()->sole()->id), 'handle']);

    Http::assertNothingSent();
    expect(WebhookDelivery::query()->sole())->status->toBe('failed')->error->toContain('paused');
});

it('does not follow redirects', function (): void {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data'])]);
    Queue::fake();
    Webhook::factory()->create(['events' => ['thing.happened']]);
    app(PublishWebhookEvent::class)->handle('thing.happened', ['id' => 'abc']);
    app()->call([new DeliverWebhook(WebhookDelivery::query()->sole()->id), 'handle']);

    Http::assertSentCount(1);
    expect(WebhookDelivery::query()->sole())->status->toBe('pending')->response_status->toBe(302);
});

it('refuses internal, plain HTTP and credential-carrying addresses', function (string $url, array $addresses): void {
    webhookResolvesTo(...$addresses);

    expect(app(WebhookUrlGuard::class)->allows($url))->toBeFalse();
})->with([
    'plain HTTP' => ['http://hooks.example.com/in', ['203.0.113.10']],
    'loopback' => ['https://hooks.example.com/in', ['127.0.0.1']],
    'cloud metadata' => ['https://hooks.example.com/in', ['169.254.169.254']],
    'private network' => ['https://hooks.example.com/in', ['10.0.0.5']],
    'carrier-grade NAT' => ['https://hooks.example.com/in', ['100.100.100.200']],
    'one internal answer among public ones' => ['https://hooks.example.com/in', ['203.0.113.10', '192.168.1.1']],
    'IPv6 loopback literal' => ['https://[::1]/in', []],
    'unknown host' => ['https://hooks.example.com/in', []],
    'credentials' => ['https://user:pass@hooks.example.com/in', ['203.0.113.10']],
]);

it('allows private networks and plain HTTP only when the installation says so', function (): void {
    webhookResolvesTo('10.0.0.5');
    expect(app(WebhookUrlGuard::class)->allows('https://hooks.internal.example/in'))->toBeFalse();

    config(['core.webhooks.allow_private_networks' => true, 'core.webhooks.allow_http' => true]);

    expect(app(WebhookUrlGuard::class)->target('http://hooks.internal.example:8080/in'))->toBe(['host' => 'hooks.internal.example', 'port' => 8080, 'address' => '10.0.0.5'])
        ->and(app(WebhookUrlGuard::class)->allows('https://hooks.example.com/in'))->toBeTrue();
    webhookResolvesTo('169.254.169.254');
    expect(app(WebhookUrlGuard::class)->allows('https://hooks.example.com/in'))->toBeFalse();
});

it('checks the address again at delivery, against a DNS answer that changed', function (): void {
    Http::fake();
    Queue::fake();
    Webhook::factory()->create(['events' => ['thing.happened']]);
    app(PublishWebhookEvent::class)->handle('thing.happened', ['id' => 'abc']);
    webhookResolvesTo('127.0.0.1');

    app()->call([new DeliverWebhook(WebhookDelivery::query()->sole()->id), 'handle']);

    Http::assertNothingSent();
    expect(WebhookDelivery::query()->sole()->error)->toContain('not allowed');
});

it('lets administrators add a webhook, shown its secret once, and send a test', function (): void {
    Filament::setCurrentPanel('admin');
    Http::fake();
    $this->actingAs(webhookAdmin($this->organization));

    $form = ['name' => 'Chat', 'url' => 'https://hooks.example.com/chat', 'events' => ['thing.happened'], 'is_active' => true];
    Livewire::test(ManageWebhooks::class)->callAction('create', data: [...$form, 'url' => 'https://127.0.0.1/in'])->assertHasActionErrors(['url']);
    Livewire::test(ManageWebhooks::class)->callAction('create', data: [...$form, 'events' => ['not.an.event']])->assertHasActionErrors(['events.0']);
    expect(Webhook::query()->count())->toBe(0);
    Livewire::test(ManageWebhooks::class)->callAction('create', data: $form)->assertHasNoActionErrors()->assertNotified();

    $webhook = Webhook::query()->sole();
    expect($webhook->secret)->toStartWith('whsec_')
        ->and(AuditLog::query()->where('auditable_id', $webhook->id)->get()->pluck('new_values')->flatten()->all())->not->toContain($webhook->secret);

    $old = $webhook->secret;
    Livewire::test(ManageWebhooks::class)->callTableAction('rotate', $webhook)->callTableAction('test', $webhook);

    expect($webhook->refresh()->secret)->not->toBe($old);
    expect(WebhookDelivery::query()->sole())->event->toBe('webhook.test')->status->toBe('delivered');
});

it('lets administrators send a failed delivery again', function (): void {
    Filament::setCurrentPanel('admin');
    Http::fake(['*' => Http::response('', 200)]);
    Queue::fake();
    Webhook::factory()->create(['events' => ['thing.happened']]);
    app(PublishWebhookEvent::class)->handle('thing.happened', ['id' => 'abc']);
    $delivery = WebhookDelivery::query()->sole();
    $delivery->forceFill(['status' => 'failed', 'attempts' => 6, 'response_status' => 500])->save();
    $this->actingAs(webhookAdmin($this->organization));

    Livewire::test(ListWebhookDeliveries::class)->assertCanSeeTableRecords([$delivery])->callTableAction('retry', $delivery);

    expect($delivery->refresh())->status->toBe('pending')->attempts->toBe(0);
    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->deliveryId === $delivery->id);
});

it('keeps webhooks to administrators of the organization', function (): void {
    Filament::setCurrentPanel('admin');
    $agent = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
    $agent->assignRole(DefaultRole::TeamLead->value);
    $ours = Webhook::factory()->create(['name' => 'Our hook']);
    $other = Organization::factory()->create();
    app(OrganizationContext::class)->runAs($other, fn () => Webhook::factory()->create(['name' => 'Their hook']));

    $this->actingAs($agent)->get('/admin/webhooks')->assertForbidden();
    $this->actingAs($agent)->get('/admin/webhook-deliveries')->assertForbidden();

    $this->actingAs(webhookAdmin($this->organization));
    Livewire::test(ManageWebhooks::class)->assertCanSeeTableRecords([$ours])->assertSee('Our hook')->assertDontSee('Their hook');
});
