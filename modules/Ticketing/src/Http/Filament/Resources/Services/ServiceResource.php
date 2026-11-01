<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Filament\Resources\Services;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Components\FormSchemaEditor;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\Service;
use Modules\Ticketing\Http\Filament\Resources\Services\Pages\ManageServices;

final class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $slug = 'ticketing/services';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('ticketing::admin.services.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ticketing::admin.services.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('ticketing::admin.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('ticketing::admin.name'))->required()->maxLength(100),
            Textarea::make('description')->label(__('ticketing::admin.description'))->maxLength(2000),
            Select::make('category_id')->label(__('ticketing::ui.fields.category'))
                ->options(fn (): array => self::options(Category::query()->where('is_active', true)))
                ->in(fn (): array => array_keys(self::options(Category::query()->where('is_active', true)))),
            Select::make('queue_id')->label(__('ticketing::ui.fields.queue'))
                ->helperText(__('ticketing::admin.services.queue_help'))
                ->options(fn (): array => self::options(Queue::query()->where('is_active', true)))
                ->in(fn (): array => array_keys(self::options(Queue::query()->where('is_active', true)))),
            Select::make('impact')->label(__('ticketing::ui.fields.impact'))
                ->options(array_combine(array_map(fn (Impact $i): string => $i->value, Impact::cases()), array_map(fn (Impact $i): string => $i->label(), Impact::cases())))
                ->required()->default(Impact::Low->value),
            Select::make('urgency')->label(__('ticketing::ui.fields.urgency'))
                ->options(array_combine(array_map(fn (Urgency $u): string => $u->value, Urgency::cases()), array_map(fn (Urgency $u): string => $u->label(), Urgency::cases())))
                ->required()->default(Urgency::Low->value),
            Select::make('owner_id')->label(__('ticketing::admin.services.owner'))
                ->options(fn (): array => self::memberOptions())
                ->in(fn (): array => array_keys(self::memberOptions()))
                ->searchable(),
            TextInput::make('sort_order')->label(__('ticketing::admin.sort_order'))->integer()->minValue(0)->maxValue(10000)->default(0),
            Toggle::make('is_active')->label(__('ticketing::admin.active'))->default(true),
            Section::make(__('ticketing::admin.services.form'))
                ->description(__('ticketing::admin.services.form_help'))
                ->schema([
                    FormSchemaEditor::make('form_schema'),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['queue', 'category']))
            ->columns([
                TextColumn::make('name')->label(__('ticketing::admin.name'))->searchable()->sortable(),
                TextColumn::make('category.name')->label(__('ticketing::ui.fields.category')),
                TextColumn::make('queue.name')->label(__('ticketing::ui.fields.queue')),
                IconColumn::make('is_active')->label(__('ticketing::admin.active'))->boolean(),
                TextColumn::make('sort_order')->label(__('ticketing::admin.sort_order'))->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageServices::route('/')];
    }

    /**
     * @param  Builder<Category>|Builder<Queue>  $query
     * @return array<string, string>
     */
    private static function options(Builder $query): array
    {
        /** @var array<string, string> $options */
        $options = $query->orderBy('name')->pluck('name', 'id')->all();

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function memberOptions(): array
    {
        $organizationId = app(OrganizationContext::class)->id();

        /** @var array<string, string> $options */
        $options = User::query()->where('is_active', true)
            ->whereHas('organizations', fn (Builder $query) => $query->whereKey($organizationId))
            ->orderBy('name')->pluck('name', 'id')->all();

        return $options;
    }
}
