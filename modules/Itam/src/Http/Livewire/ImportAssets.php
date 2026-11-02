<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Core\Application\Imports\ImportHasErrors;
use Modules\Core\Application\Imports\ImportRow;
use Modules\Core\Domain\Models\User;
use Modules\Core\Infrastructure\Imports\SpreadsheetReader;
use Modules\Core\Infrastructure\Imports\SpreadsheetUnreadable;
use Modules\Itam\Application\Actions\ProvisionDefaultCategories;
use Modules\Itam\Application\Import\AssetImport;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\Category;
use Throwable;

/**
 * Import from CSV or Excel: upload, preview what each row will do, then import
 * every row or none. The file is read again when importing: the preview shown
 * in the browser is never trusted.
 */
#[Layout('core::components.layouts.app')]
final class ImportAssets extends Component
{
    use WithFileUploads;

    public const int MAX_ROWS = 2000;

    public const int MAX_KILOBYTES = 5120;

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    /** @var list<array{line: int, label: string, action: string, errors: list<string>}> */
    public array $preview = [];

    public ?int $imported = null;

    public function mount(ProvisionDefaultCategories $provision): void
    {
        $this->authorize('create', Asset::class);
        $provision->handle();
    }

    public function updatedFile(): void
    {
        $this->reset('preview', 'imported');
    }

    public function analyse(SpreadsheetReader $reader, AssetImport $import): void
    {
        $this->authorize('create', Asset::class);
        $rows = $this->rows($reader);

        if ($rows === null) {
            return;
        }

        $this->preview = array_map(fn (ImportRow $row): array => $row->toArray(), $import->analyse($rows));
        $this->imported = null;
    }

    public function import(SpreadsheetReader $reader, AssetImport $import): void
    {
        $this->authorize('create', Asset::class);
        $rows = $this->rows($reader);
        $actor = auth()->user();

        if ($rows === null || ! $actor instanceof User) {
            return;
        }

        try {
            $this->imported = $import->apply($rows, $actor);
        } catch (ImportHasErrors) {
            $this->analyse($reader, $import);
            $this->addError('file', __('itam::import.has_errors'));

            return;
        }

        $this->reset('file', 'preview');
    }

    public function render(): View
    {
        $errors = count(array_filter($this->preview, fn (array $row): bool => $row['action'] === ImportRow::ERROR));

        return view('itam::livewire.import', [
            'columns' => AssetImport::COLUMNS,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'errorCount' => $errors,
            'counts' => array_count_values(array_column($this->preview, 'action')),
            'shown' => array_slice(array_values(array_filter($this->preview, fn (array $row): bool => $errors === 0 || $row['action'] === ImportRow::ERROR)), 0, 200),
        ])->title(__('itam::import.title'));
    }

    /**
     * @return list<array{line: int, values: array<string, string>}>|null
     */
    private function rows(SpreadsheetReader $reader): ?array
    {
        $this->validate(['file' => ['required', 'file', 'max:'.self::MAX_KILOBYTES]]);
        $file = $this->file;

        if (! $file instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            return $reader->readUpload($file, self::MAX_ROWS);
        } catch (SpreadsheetUnreadable $exception) {
            $this->addError('file', __('itam::import.'.$exception->reason, ['max' => self::MAX_ROWS]));

            return null;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('file', __('itam::import.unreadable'));

            return null;
        }
    }
}
