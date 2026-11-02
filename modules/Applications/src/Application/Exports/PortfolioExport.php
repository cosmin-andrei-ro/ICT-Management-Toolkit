<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Exports;

use Generator;
use Modules\Applications\Application\Options\OptionCatalog;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Domain\Sheet\DataCategories;
use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Models\Export;

/**
 * The application portfolio for auditors and architects: one row per
 * application, then the data processed, integrations, components and
 * certificates of the documentation in force. Secrets and technical accounts
 * stay out of it.
 */
final readonly class PortfolioExport implements ExportSource
{
    public const string TYPE = 'applications.portfolio';

    public function __construct(private OptionCatalog $options) {}

    public function sheets(Export $export): array
    {
        $current = array_values(Sheet::query()->with('application')->where('status', SheetStatus::Approved->value)->get()
            ->filter(fn (Sheet $sheet): bool => $sheet->application->deleted_at === null)
            ->sortBy(fn (Sheet $sheet): string => mb_strtolower($sheet->application->name))->all());
        $byApplication = [];

        foreach ($current as $sheet) {
            $byApplication[$sheet->application_id] = $sheet;
        }

        return [
            new ExportSheet(__('applications::export.applications'), [
                __('applications::applications.fields.code'), __('applications::applications.fields.name'), __('applications::applications.fields.department'),
                __('applications::applications.fields.business_owner'), __('applications::applications.fields.technical_owner'), __('applications::applications.fields.status'),
                __('applications::applications.fields.criticality'), __('applications::applications.fields.version'), __('applications::export.approved_at'),
                __('applications::applications.fields.next_review'), __('applications::export.data_classification'), __('applications::export.exposure'),
            ], $this->applicationRows($byApplication)),
            new ExportSheet(__('applications::export.data'), [__('applications::applications.fields.code'), __('applications::data.category'), __('applications::data.elements_processed')], $this->dataRows($current)),
            new ExportSheet(__('applications::export.integrations'), [
                __('applications::applications.fields.code'), __('applications::sheet.fields.2_4.integrations.name'), __('applications::sheet.fields.2_4.integrations.system'),
                __('applications::sheet.fields.2_4.integrations.direction'), __('applications::sheet.fields.2_4.integrations.type'), __('applications::sheet.fields.2_4.integrations.data'),
                __('applications::sheet.fields.2_4.integrations.frequency'),
            ], $this->tableRows($current, '2_4', 'integrations', ['name' => null, 'system' => null, 'direction' => 'integration_direction', 'type' => 'integration_type', 'data' => null, 'frequency' => 'integration_frequency'])),
            new ExportSheet(__('applications::export.components'), [
                __('applications::applications.fields.code'), __('applications::sheet.fields.5_2.components.component'), __('applications::sheet.fields.5_2.components.server'),
                __('applications::sheet.fields.5_2.components.ip'), __('applications::sheet.fields.5_2.components.os'), __('applications::sheet.fields.5_2.components.environment'),
            ], $this->tableRows($current, '5_2', 'components', ['component' => 'component_types', 'server' => null, 'ip' => null, 'os' => 'operating_systems', 'environment' => 'environments'])),
            new ExportSheet(__('applications::export.certificates'), [
                __('applications::applications.fields.code'), __('applications::sheet.fields.8_1.certificates.name'), __('applications::sheet.fields.8_1.certificates.used_for'),
                __('applications::sheet.fields.8_1.certificates.issuer'), __('applications::sheet.fields.8_1.certificates.expires_on'), __('applications::sheet.fields.8_1.certificates.owner'),
            ], $this->tableRows($current, '8_1', 'certificates', ['name' => null, 'used_for' => null, 'issuer' => null, 'expires_on' => null, 'owner' => null])),
        ];
    }

    public function filename(Export $export): string
    {
        return 'application-portfolio-'.$export->created_at?->format('Y-m-d');
    }

    /**
     * @param  array<string, Sheet>  $current  By application id.
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function applicationRows(array $current): Generator
    {
        foreach (Application::query()->with(['department', 'businessOwner', 'technicalOwner'])->orderBy('name')->lazy(200) as $application) {
            $sheet = $current[$application->id] ?? null;
            $classification = $sheet?->section('2_1')['classification'] ?? null;
            $exposure = $sheet?->section('1_3')['exposure'] ?? [];

            yield [
                $application->code,
                $application->name,
                $application->department?->name,
                $application->businessOwner?->name,
                $application->technicalOwner?->name,
                $this->options->label('application_status', $application->status),
                $this->options->label('business_criticality', $application->criticality),
                $application->current_version,
                $application->approved_at?->toDateString(),
                $application->next_review_on?->toDateString(),
                is_string($classification) ? $this->options->label('data_classification', $classification) : null,
                implode(', ', array_map(fn (mixed $choice): string => is_string($choice) ? (string) __('applications::sheet.choices.exposure.'.$choice) : '', is_array($exposure) ? $exposure : [])),
            ];
        }
    }

    /**
     * @param  list<Sheet>  $current
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function dataRows(array $current): Generator
    {
        foreach ($current as $sheet) {
            $categories = $sheet->section('2_1')['categories'] ?? [];

            foreach (DataCategories::all() as $category => $elements) {
                $chosen = is_array($categories) && is_array($categories[$category] ?? null) ? array_intersect($elements, array_filter($categories[$category], is_string(...))) : [];

                if ($chosen !== []) {
                    yield [
                        $sheet->application->code,
                        (string) __('applications::data.categories.'.$category),
                        implode(', ', array_map(fn (string $element): string => (string) __('applications::data.elements.'.$category.'.'.$element), $chosen)),
                    ];
                }
            }
        }
    }

    /**
     * @param  list<Sheet>  $current
     * @param  array<string, ?string>  $columns  Column key => dropdown list, if any.
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function tableRows(array $current, string $section, string $field, array $columns): Generator
    {
        foreach ($current as $sheet) {
            $rows = $sheet->section($section)[$field] ?? [];

            foreach (is_array($rows) ? $rows : [] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $values = [$sheet->application->code];

                foreach ($columns as $column => $list) {
                    $value = is_string($row[$column] ?? null) ? $row[$column] : null;
                    $values[] = $list !== null ? $this->options->label($list, $value) : $value;
                }

                yield $values;
            }
        }
    }
}
