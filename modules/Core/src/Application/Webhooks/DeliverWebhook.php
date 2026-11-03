<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Webhooks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Factory as Http;
use Modules\Core\Domain\Models\WebhookDelivery;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Throwable;

/**
 * Sends one delivery, signed: `X-Webhook-Signature: t=<unix time>,v1=<hex>`,
 * where v1 is the HMAC-SHA256 of "<t>.<body>" with the webhook's secret.
 * A 2xx answer delivers it; anything else is tried again later, five times
 * at most, then the delivery is marked failed. Redirects are not followed.
 */
final class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /** Seconds before each retry. */
    public const array BACKOFF = [60, 300, 1800, 7200, 43200];

    public int $tries = 1;

    public function __construct(public readonly string $deliveryId) {}

    public function handle(Http $http, WebhookUrlGuard $guard, Config $config): void
    {
        $delivery = WebhookDelivery::query()->withoutGlobalScope(OrganizationScope::class)->with(['webhook' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class)])->find($this->deliveryId);

        if ($delivery === null || $delivery->status !== 'pending') {
            return;
        }

        // Paused: nothing more is sent, and the delivery can be sent again from the list.
        if (! $delivery->webhook->is_active) {
            $delivery->forceFill(['status' => 'failed', 'error' => 'The webhook is paused.', 'next_attempt_at' => null])->save();

            return;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->getTimestamp();
        $target = $guard->target($delivery->webhook->url);
        $status = null;
        $error = null;

        if ($target === null) {
            $error = 'The address is not allowed (HTTPS, public network).';
        } else {
            try {
                $timeout = $config->get('core.webhooks.timeout');
                $response = $http->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'ICT-Management-Toolkit-Webhooks',
                    'X-Webhook-Event' => $delivery->event,
                    'X-Webhook-Delivery' => $delivery->id,
                    'X-Webhook-Signature' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $delivery->webhook->secret),
                ])->withOptions([
                    'allow_redirects' => false,
                    // Connect to the address just checked, not to a fresh DNS answer.
                    'curl' => [CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.(str_contains($target['address'], ':') ? '['.$target['address'].']' : $target['address'])]],
                ])->timeout(is_int($timeout) ? $timeout : 10)->withBody($body, 'application/json')->post($delivery->webhook->url);
                $status = $response->status();
            } catch (Throwable $exception) {
                $error = mb_substr($exception->getMessage(), 0, 500);
            }
        }

        $attempts = $delivery->attempts + 1;

        if ($status !== null && $status >= 200 && $status < 300) {
            $delivery->forceFill(['status' => 'delivered', 'attempts' => $attempts, 'response_status' => $status, 'error' => null, 'delivered_at' => now(), 'next_attempt_at' => null])->save();

            return;
        }

        $delay = self::BACKOFF[$attempts - 1] ?? null;
        $delivery->forceFill([
            'status' => $delay === null ? 'failed' : 'pending',
            'attempts' => $attempts,
            'response_status' => $status,
            'error' => $error ?? ('HTTP '.$status),
            'next_attempt_at' => $delay !== null ? now()->addSeconds($delay) : null,
        ])->save();

        if ($delay !== null) {
            self::dispatch($delivery->id)->delay($delay);
        }
    }
}
