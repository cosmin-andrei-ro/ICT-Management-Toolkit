<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Ticketing\Application\Monitoring\AlertSignal;

/**
 * An alert in the toolkit's own format, which Zabbix, Nagios and others send
 * through their webhook templates (ADR 0067).
 */
final class StoreMonitoringAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Monitoring opens and resolves incidents and links items: agent work.
        return $this->user()?->can('ticketing.ticket.work') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Stable for one problem, e.g. "<host>:<check>": repeats find the open incident by it.
            'key' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['firing', 'resolved'])],
            'title' => ['required_if:status,firing', 'nullable', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'severity' => ['nullable', Rule::in(AlertSignal::SEVERITIES)],
            'item' => ['nullable', 'string', 'max:255'],
            'item_id' => ['nullable', 'uuid'],
            'source' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function signal(): AlertSignal
    {
        $optional = fn (string $field): ?string => $this->filled($field) ? $this->string($field)->trim()->toString() : null;

        return new AlertSignal(
            key: $this->string('key')->trim()->toString(),
            firing: $this->string('status')->toString() === 'firing',
            title: $optional('title') ?? $this->string('key')->trim()->toString(),
            description: $optional('description'),
            severity: $optional('severity') ?? 'major',
            item: $optional('item'),
            itemId: $optional('item_id'),
            source: $optional('source'),
        );
    }
}
