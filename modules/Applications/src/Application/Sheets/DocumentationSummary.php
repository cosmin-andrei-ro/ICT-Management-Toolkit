<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Sheets;

use Modules\Applications\Application\Options\OptionCatalog;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Core\Domain\Events\ApplicationDocumented;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Time\Duration;

/**
 * What other modules learn when an application's documentation comes into
 * force: the summary the documentation page shows (owners, support,
 * components, recovery objectives), values and labels already resolved.
 */
final readonly class DocumentationSummary
{
    public function __construct(
        private OptionCatalog $options,
        private ConfigurationItems $items,
        private BusinessProcesses $processes,
    ) {}

    public function event(Sheet $sheet, Application $application, User $actor): ApplicationDocumented
    {
        $application->loadMissing(['businessOwner', 'technicalOwner', 'deputy']);
        $owners = [];

        foreach (['business_owner' => $application->businessOwner, 'technical_owner' => $application->technicalOwner, 'deputy' => $application->deputy] as $role => $person) {
            if ($person !== null) {
                $owners[] = ['role' => (string) __('applications::applications.fields.'.$role), 'name' => $person->name];
            }
        }

        $vendor = $sheet->section('1_2_2');
        $hours = $sheet->section('1_6')['support_hours'] ?? null;
        $supplierId = $vendor['supplier'] ?? null;
        $support = array_values(array_filter([
            ['label' => (string) __('applications::sheet.fields.1_2_2.supplier'), 'value' => is_string($supplierId) ? (Supplier::query()->find($supplierId)->name ?? '') : ''],
            ['label' => (string) __('applications::sheet.fields.1_2_2.support_portal'), 'value' => is_string($vendor['support_portal'] ?? null) ? $vendor['support_portal'] : ''],
            ['label' => (string) __('applications::sheet.fields.1_2_2.support_email'), 'value' => is_string($vendor['support_email'] ?? null) ? $vendor['support_email'] : ''],
            ['label' => (string) __('applications::sheet.fields.1_6.support_hours'), 'value' => is_string($hours) ? ($this->options->label('support_hours', $hours) ?? '') : ''],
        ], fn (array $row): bool => $row['value'] !== ''));

        $processIds = array_values(array_filter((array) ($sheet->section('10_1')['processes'] ?? []), is_string(...)));
        $description = $sheet->section('1_1')['description'] ?? '';

        return new ApplicationDocumented(
            organizationId: $application->organization_id,
            applicationId: $application->id,
            configurationItemId: $application->configuration_item_id,
            code: $application->code,
            name: $application->name,
            version: (string) $sheet->versionLabel(),
            description: is_string($description) ? $description : '',
            criticality: $this->options->label('business_criticality', $application->criticality),
            owners: $owners,
            ownerIds: array_values(array_filter([$application->business_owner_id, $application->technical_owner_id, $application->deputy_id], is_string(...))),
            support: $support,
            components: array_map(fn ($item): string => $item->name, $this->items->linkedTo($sheet)),
            processes: array_values(array_map(fn ($process): array => [
                'name' => $process->name,
                'rto' => $process->rtoMinutes !== null ? Duration::format($process->rtoMinutes) : null,
                'rpo' => $process->rpoMinutes !== null ? Duration::format($process->rpoMinutes) : null,
            ], $processIds !== [] ? $this->processes->find($processIds) : [])),
            applicationUrl: route('applications.show', $application),
            documentUrl: route('applications.sheets.document', $sheet),
            actor: $actor,
        );
    }
}
