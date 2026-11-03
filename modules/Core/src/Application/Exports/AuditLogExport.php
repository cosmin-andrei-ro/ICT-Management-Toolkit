<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Exports;

use Generator;
use Modules\Core\Application\Audit\AuditChanges;
use Modules\Core\Application\Audit\AuditLogQuery;
use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Livewire\Audit\AuditLogIndex;

/**
 * The audit log as filtered on screen, for auditors (ISO/IEC 27001 8.15):
 * one row per changed field, with who, when, from where.
 */
final readonly class AuditLogExport implements ExportSource
{
    public const string TYPE = 'core.audit_log';

    private const int MAX_ENTRIES = 50000;

    public function __construct(private AuditLogQuery $query) {}

    public function sheets(Export $export): array
    {
        return [
            new ExportSheet(__('core::audit.title'), [
                __('core::audit.fields.when'), __('core::audit.fields.event'), __('core::audit.fields.person'), __('core::audit.fields.record_type'), __('core::audit.fields.record'),
                __('core::audit.fields.field'), __('core::audit.fields.old'), __('core::audit.fields.new'), __('core::audit.fields.ip_address'), __('core::audit.fields.request'),
            ], $this->rows($export)),
        ];
    }

    public function filename(Export $export): string
    {
        return 'audit-log-'.$export->created_at?->format('Y-m-d');
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function rows(Export $export): Generator
    {
        $reader = User::query()->find($export->user_id);

        if ($reader === null) {
            return;
        }

        $parameters = $export->parameters;
        $filters = [];

        foreach (['category', 'event', 'person', 'type', 'from', 'until'] as $key) {
            if (isset($parameters[$key]) && is_string($parameters[$key])) {
                $filters[$key] = $parameters[$key];
            }
        }

        $timezone = config()->string('app.display_timezone');
        $people = [];
        // A cursor keeps the limit (lazy() would page over it); entries are handled by batches so names load once per batch.
        $entries = $this->query->visible($reader, $export->organization_id, $filters)->limit(self::MAX_ENTRIES)->cursor();

        foreach ($entries->chunk(500) as $batch) {
            $changes = AuditChanges::for($batch->all());

            foreach ($batch as $entry) {
                if ($entry->user_id !== null && ! array_key_exists($entry->user_id, $people)) {
                    $person = User::query()->find($entry->user_id, ['name', 'email']);
                    $people[$entry->user_id] = $person !== null ? $person->name.' <'.$person->email.'>' : null;
                }

                $base = [
                    $entry->created_at->timezone($timezone)->format('Y-m-d H:i:s'),
                    $entry->event->label(),
                    $entry->user_id !== null ? $people[$entry->user_id] : null,
                    AuditLogIndex::typeLabel($entry->auditable_type),
                    $entry->auditable_id,
                ];
                $rows = $changes->rows($entry);

                foreach ($rows === [] ? [['field' => null, 'old' => null, 'new' => null]] : $rows as $row) {
                    yield [...$base, $row['field'], $row['old'], $row['new'], $entry->ip_address, $entry->request_id];
                }
            }
        }
    }
}
