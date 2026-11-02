<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Sheets;

use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Modules\Applications\Application\Options\OptionCatalog;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Domain\Sheet\SheetSchema;
use Modules\Applications\Http\Controllers\DiagramImageController;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;

/**
 * Everything the full documentation of a version shows, chapter by chapter,
 * as the template lays it out: for the printable page and the PDF. In a PDF
 * the diagram images are embedded, since the PDF service fetches nothing.
 */
final readonly class SheetDocument
{
    /** Larger images are left out of the PDF rather than slowing it down. */
    private const int MAX_EMBEDDED_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private OptionCatalog $options,
        private SheetForm $sheetForm,
        private ConfigurationItems $items,
        private BusinessProcesses $processes,
        private LinkedRecords $linkedRecords,
        private Filesystem $filesystem,
    ) {}

    /**
     * @return array<string, mixed> View data for applications::document.
     */
    public function data(Sheet $sheet, User $viewer, bool $embedImages): array
    {
        $sheet->loadMissing(['application.department', 'application.businessOwner', 'application.technicalOwner', 'application.deputy', 'approvals.user', 'submitter']);
        $application = $sheet->application;
        $linkedItems = $this->items->linkedTo($sheet);
        $itemIds = array_map(fn ($item): string => $item->id, $linkedItems);
        $processIds = array_values(array_filter((array) ($sheet->section('10_1')['processes'] ?? []), is_string(...)));
        $files = Attachment::query()->where('attachable_type', 'applications.application')->where('attachable_id', $sheet->application_id)->get()->keyBy('id');

        return [
            'sheet' => $sheet,
            'application' => $application,
            'organization' => Organization::query()->find($sheet->organization_id),
            'chapters' => SheetSchema::chapters(),
            'history' => Sheet::query()->with(['submitter', 'author', 'approvals.user'])->where('application_id', $application->id)->whereNotNull('major')
                ->orderBy('major')->orderBy('minor')->get(),
            'options' => $this->options,
            'people' => User::query()->whereHas('organizations', fn ($query) => $query->whereKey($sheet->organization_id))->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'contracts' => Contract::query()->orderBy('title')->limit(500)->get(),
            'form' => [],
            'editable' => false,
            'matrix' => $this->sheetForm->matrixAxes($sheet),
            'integrations' => self::rows($sheet, '2_4', 'integrations'),
            'firewallRules' => self::rows($sheet, '4_3', 'rules'),
            'files' => $files,
            'imageSrc' => $embedImages ? $this->embedded(...) : fn (Attachment $image): string => route('applications.diagrams.image', $image),
            'cmdbAvailable' => $this->items->isAvailable(),
            'linkedItems' => $linkedItems,
            'itemCandidates' => [],
            'processesAvailable' => $this->processes->isAvailable(),
            'processCandidates' => [],
            'insights' => [
                'processes' => $processIds !== [] ? $this->processes->find($processIds) : [],
                'dependencies' => $this->items->recoveryProfiles($this->items->dependencyIdsOf($itemIds)),
                'plans' => $this->linkedRecords->resolve('bcm.plan_version', $this->items->recordIdsLinkedTo($itemIds, 'bcm.plan_version'), $viewer),
            ],
            'pdf' => $embedImages,
        ];
    }

    /**
     * @return list<array<mixed>>
     */
    private static function rows(Sheet $sheet, string $section, string $field): array
    {
        $rows = $sheet->section($section)[$field] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, is_array(...))) : [];
    }

    private function embedded(Attachment $image): string
    {
        if (! in_array($image->mime_type, DiagramImageController::IMAGE_TYPES, true) || $image->size > self::MAX_EMBEDDED_BYTES) {
            return '';
        }

        $content = $this->filesystem->disk($image->disk)->get($image->path);

        return is_string($content) ? 'data:'.$image->mime_type.';base64,'.base64_encode($content) : '';
    }
}
