<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Actions\SavePriorityMatrix;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Priority;
use Modules\Ticketing\Domain\Enums\Urgency;

/**
 * Impact × urgency → priority for the current organization.
 *
 * @property-read Schema $form
 */
final class PriorityMatrix extends Page
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static ?string $slug = 'ticketing/priority-matrix';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    public static function getNavigationGroup(): string
    {
        return __('ticketing::admin.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('ticketing::admin.priority_matrix.title');
    }

    public function getTitle(): string
    {
        return __('ticketing::admin.priority_matrix.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('ticketing.settings.manage');
    }

    public function mount(SavePriorityMatrix $matrix): void
    {
        abort_unless(self::canAccess(), 403);

        $cells = $matrix->current(app(OrganizationContext::class)->requireId());
        $this->form->fill(array_combine(
            array_map(fn (string $key): string => str_replace('.', '_', $key), array_keys($cells)),
            $cells,
        ));
    }

    public function form(Schema $schema): Schema
    {
        $options = [];
        foreach (Priority::cases() as $priority) {
            $options[$priority->value] = $priority->label();
        }

        $rows = [];
        foreach (Impact::cases() as $impact) {
            $selects = [];
            foreach (Urgency::cases() as $urgency) {
                $selects[] = Select::make("{$impact->value}_{$urgency->value}")
                    ->label(__('ticketing::admin.priority_matrix.urgency', ['urgency' => ucfirst($urgency->value)]))
                    ->options($options)
                    ->in(array_keys($options))
                    ->required()
                    ->selectablePlaceholder(false);
            }

            $rows[] = Section::make(__('ticketing::admin.priority_matrix.impact', ['impact' => ucfirst($impact->value)]))
                ->schema([Grid::make(3)->schema($selects)]);
        }

        return $schema->components($rows)->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label(__('ticketing::admin.save'))->submit('save')]),
                ]),
        ]);
    }

    public function save(SavePriorityMatrix $matrix): void
    {
        abort_unless(self::canAccess(), 403);

        /** @var array<string, string> $state */
        $state = $this->form->getState();

        $cells = [];
        foreach ($state as $key => $value) {
            $cells[str_replace('_', '.', $key)] = Priority::from($value);
        }

        $organization = Organization::query()->findOrFail(app(OrganizationContext::class)->requireId());
        $matrix->handle($organization, $cells);

        Notification::make()->success()->title(__('ticketing::admin.saved'))->send();
    }
}
