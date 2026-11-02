<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Cmdb\Application\Actions\ProvisionDefaultTypes;
use Modules\Cmdb\Application\Import\ItemImport;
use Modules\Cmdb\Application\Import\RelationshipImport;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Application\Imports\ImportHasErrors;
use Modules\Core\Application\Imports\ImportRow;
use Modules\Core\Infrastructure\Imports\SpreadsheetReader;
use Modules\Core\Infrastructure\Imports\SpreadsheetUnreadable;
use Throwable;

/**
 * Import from CSV or Excel: upload, preview what each row will do, then import
 * every row or none. The file is read again when importing: the preview shown
 * in the browser is never trusted.
 */
#[Layout('core::components.layouts.app')]
final class ImportPage extends Component
{
    use WithFileUploads;

    public const int MAX_ROWS = 2000;

    public const int MAX_KILOBYTES = 5120;

    public string $kind = 'items';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    /** @var list<array{line: int, label: string, action: string, errors: list<string>}> */
    public array $preview = [];

    public ?int $imported = null;

    public function mount(ProvisionDefaultTypes $provision): void
    {
        $this->authorize('create', ConfigurationItem::class);
        $provision->handle();
    }

    public function updatedFile(): void
    {
        $this->reset('preview', 'imported');
    }

    public function updatedKind(): void
    {
        $this->reset('preview', 'imported');
    }

    public function analyse(SpreadsheetReader $reader, ItemImport $items, RelationshipImport $relationships): void
    {
        $this->authorize('create', ConfigurationItem::class);
        $rows = $this->rows($reader);

        if ($rows === null) {
            return;
        }

        $plan = $this->kind === 'relationships' ? $relationships->analyse($rows) : $items->analyse($rows);
        $this->preview = array_map(fn (ImportRow $row): array => $row->toArray(), $plan);
        $this->imported = null;
    }

    public function import(SpreadsheetReader $reader, ItemImport $items, RelationshipImport $relationships): void
    {
        $this->authorize('create', ConfigurationItem::class);
        $rows = $this->rows($reader);

        if ($rows === null) {
            return;
        }

        try {
            $this->imported = $this->kind === 'relationships' ? $relationships->apply($rows) : $items->apply($rows);
        } catch (ImportHasErrors) {
            $this->analyse($reader, $items, $relationships);
            $this->addError('file', __('cmdb::import.has_errors'));

            return;
        }

        $this->reset('file', 'preview');
    }

    public function render(): View
    {
        $errors = count(array_filter($this->preview, fn (array $row): bool => $row['action'] === ImportRow::ERROR));

        return view('cmdb::livewire.import', [
            'types' => CiType::query()->where('is_active', true)->orderBy('name')->get(),
            'errorCount' => $errors,
            'counts' => array_count_values(array_column($this->preview, 'action')),
            'shown' => array_slice(array_values(array_filter($this->preview, fn (array $row): bool => $errors === 0 || $row['action'] === ImportRow::ERROR)), 0, 200),
        ])->title(__('cmdb::import.title'));
    }

    /**
     * @return list<array{line: int, values: array<string, string>}>|null
     */
    private function rows(SpreadsheetReader $reader): ?array
    {
        $this->validate([
            'kind' => ['required', 'in:items,relationships'],
            'file' => ['required', 'file', 'max:'.self::MAX_KILOBYTES],
        ]);

        $file = $this->file;

        if (! $file instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            return $reader->readUpload($file, self::MAX_ROWS);
        } catch (SpreadsheetUnreadable $exception) {
            $this->addError('file', __('cmdb::import.'.$exception->reason, ['max' => self::MAX_ROWS]));

            return null;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('file', __('cmdb::import.unreadable'));

            return null;
        }
    }
}
