<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Filament\Resources\ChangeTemplates;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Ticketing\Application\Changes\ChangePlan;
use Modules\Ticketing\Domain\Changes\ChangeRisk;
use Modules\Ticketing\Domain\Models\ChangeTemplate;
use Modules\Ticketing\Http\Filament\Resources\ChangeTemplates\Pages\ManageChangeTemplates;

/**
 * Standard changes: routine work approved once, here, instead of by the CAB each time.
 */
final class ChangeTemplateResource extends Resource
{
    protected static ?string $model = ChangeTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $slug = 'ticketing/change-templates';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('ticketing::admin.change_templates.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ticketing::admin.change_templates.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('ticketing::admin.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('ticketing::admin.name'))->required()->maxLength(200),
            Select::make('risk')
                ->label(__('ticketing::changes.ui.risk'))
                ->options(fn (): array => collect(ChangeRisk::cases())->mapWithKeys(fn (ChangeRisk $risk): array => [$risk->value => $risk->label()])->all())
                ->required()
                ->default(ChangeRisk::Low->value),
            Textarea::make('description')->label(__('ticketing::admin.description'))->helperText(__('ticketing::admin.change_templates.description_help'))->required()->maxLength(ChangePlan::MAX_LENGTH)->columnSpanFull(),
            Textarea::make('implementation_plan')->label(__('ticketing::changes.ui.implementation_plan'))->required()->maxLength(ChangePlan::MAX_LENGTH)->rows(5)->columnSpanFull(),
            Textarea::make('rollback_plan')->label(__('ticketing::changes.ui.rollback_plan'))->required()->maxLength(ChangePlan::MAX_LENGTH)->rows(4)->columnSpanFull(),
            Textarea::make('test_plan')->label(__('ticketing::changes.ui.test_plan'))->maxLength(ChangePlan::MAX_LENGTH)->rows(3)->columnSpanFull(),
            Toggle::make('is_active')->label(__('ticketing::admin.active'))->helperText(__('ticketing::admin.change_templates.active_help'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('ticketing::admin.name'))->searchable()->sortable(),
                TextColumn::make('risk')->label(__('ticketing::changes.ui.risk'))->formatStateUsing(fn (ChangeRisk $state): string => $state->label()),
                IconColumn::make('is_active')->label(__('ticketing::admin.active'))->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageChangeTemplates::route('/')];
    }
}
