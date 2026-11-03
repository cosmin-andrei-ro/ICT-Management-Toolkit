<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Monitoring\ReceiveMonitoringAlert;
use Modules\Ticketing\Domain\Models\MonitoringAlert;
use Modules\Ticketing\Http\Requests\Api\StoreAlertmanagerAlertsRequest;
use Modules\Ticketing\Http\Requests\Api\StoreMonitoringAlertRequest;

/**
 * Alerts from monitoring tools (ADR 0067). A firing alert opens an incident
 * for the token's user (a service account with the agent role), linked to
 * the CMDB item it names; repeats are counted; a cleared alert resolves the
 * incident unless someone has started work on it.
 */
final readonly class MonitoringAlertController
{
    public function __construct(private ReceiveMonitoringAlert $receive) {}

    /**
     * Receive one alert in the toolkit's own format.
     *
     * `action` says what happened: `created`, `repeated`, `resolved`, `noted`
     * (cleared, but the incident is being worked on) or `ignored` (no firing
     * alert with that key).
     */
    public function store(StoreMonitoringAlertRequest $request): JsonResponse
    {
        $result = $this->receive->handle($request->signal(), $this->user($request));

        return response()->json(['data' => self::outcome($result)], 202);
    }

    /**
     * Receive a Prometheus Alertmanager notification (webhook receiver).
     */
    public function alertmanager(StoreAlertmanagerAlertsRequest $request): JsonResponse
    {
        $user = $this->user($request);
        $outcomes = array_map(fn ($signal): array => self::outcome($this->receive->handle($signal, $user)), $request->signals());

        return response()->json(['data' => $outcomes], 202);
    }

    /**
     * @param  array{action: string, alert: ?MonitoringAlert}  $result
     * @return array{action: string, alert: ?array{id: string, key: string, status: string, occurrences: int, ticket: array{id: string, number: string, url: string}}}
     */
    private static function outcome(array $result): array
    {
        $alert = $result['alert'];

        return [
            'action' => $result['action'],
            'alert' => $alert === null ? null : [
                'id' => $alert->id,
                'key' => $alert->key,
                'status' => $alert->status,
                'occurrences' => $alert->occurrences,
                'ticket' => [
                    'id' => $alert->ticket_id,
                    'number' => $alert->ticket->number,
                    'url' => route('api.v1.tickets.show', $alert->ticket_id),
                ],
            ],
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
