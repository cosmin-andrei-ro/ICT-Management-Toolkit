<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Ticketing\Application\Monitoring\AlertSignal;

/**
 * Prometheus Alertmanager's webhook payload, as it sends it (ADR 0067). The
 * item comes from the `ci` label, else `instance` (without its port), else
 * `host`; the severity from the `severity` label.
 */
final class StoreAlertmanagerAlertsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ticketing.ticket.work') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alerts' => ['required', 'array', 'min:1', 'max:100'],
            'alerts.*.status' => ['required', Rule::in(['firing', 'resolved'])],
            'alerts.*.labels' => ['required', 'array'],
            'alerts.*.labels.*' => ['nullable', 'string', 'max:1000'],
            'alerts.*.annotations' => ['nullable', 'array'],
            'alerts.*.annotations.*' => ['nullable', 'string', 'max:10000'],
            'alerts.*.fingerprint' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return list<AlertSignal>
     */
    public function signals(): array
    {
        $signals = [];

        /** @var list<array{status: string, labels: array<string, ?string>, annotations?: ?array<string, ?string>, fingerprint?: ?string}> $alerts */
        $alerts = $this->validated('alerts');

        foreach ($alerts as $alert) {
            $labels = array_filter($alert['labels'], fn (?string $value): bool => $value !== null && $value !== '');
            $annotations = $alert['annotations'] ?? [];
            ksort($labels);
            $instance = isset($labels['instance']) ? (string) preg_replace('/:\d+$/', '', $labels['instance']) : null;
            $severity = strtolower($labels['severity'] ?? '');

            $signals[] = new AlertSignal(
                key: 'alertmanager:'.(($alert['fingerprint'] ?? null) ?: sha1((string) json_encode($labels))),
                firing: $alert['status'] === 'firing',
                title: mb_substr($annotations['summary'] ?? $labels['alertname'] ?? 'Alert', 0, 200),
                description: $annotations['description'] ?? null,
                severity: in_array($severity, AlertSignal::SEVERITIES, true) ? $severity : 'major',
                item: $labels['ci'] ?? $instance ?? $labels['host'] ?? null,
                source: 'Alertmanager',
            );
        }

        return $signals;
    }
}
