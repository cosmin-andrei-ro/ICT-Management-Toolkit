<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\DirectoryGroupMappings;

use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Directory\DirectoryGroupMapping;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\DirectoryGroupMappings\Pages\ManageDirectoryGroupMappings;

/**
 * Maps AD groups to roles in the current organization. Records are scoped by the
 * model's organization scope; authorization comes from DirectoryGroupMappingPolicy.
 */
final class DirectoryGroupMappingResource extends Resource
{
    protected static ?string $model = DirectoryGroupMapping::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'group_dn';

    public static function getModelLabel(): string
    {
        return __('core::directory.mapping.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::directory.mapping.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('core::directory.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('group_dn')
                    ->label(__('core::directory.mapping.group_dn'))
                    ->helperText(__('core::directory.mapping.group_dn_help'))
                    ->required()
                    ->maxLength(1024)
                    ->regex('/\A[A-Za-z][A-Za-z0-9-]*=[^,]+(,[A-Za-z][A-Za-z0-9-]*=[^,]+)*\z/')
                    ->dehydrateStateUsing(fn (string $state): string => Str::lower(trim($state)))
                    ->rule(fn (Get $get, ?DirectoryGroupMapping $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        $duplicate = DirectoryGroupMapping::query()
                            ->where('group_dn', is_string($value) ? Str::lower(trim($value)) : '')
                            ->where('role_id', $get('role_id'))
                            ->when($record !== null, fn ($query) => $query->whereKeyNot($record?->id))
                            ->exists();

                        if ($duplicate) {
                            $fail(__('core::directory.mapping.duplicate'));
                        }
                    }),
                Select::make('role_id')
                    ->label(__('core::directory.mapping.role'))
                    ->options(fn (): array => self::assignableRoles())
                    ->in(fn (): array => array_keys(self::assignableRoles()))
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('role'))
            ->columns([
                TextColumn::make('group_dn')
                    ->label(__('core::directory.mapping.group_dn'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role.name')
                    ->label(__('core::directory.mapping.role'))
                    ->badge(),
                TextColumn::make('updated_at')
                    ->label(__('core::directory.mapping.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('group_dn')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDirectoryGroupMappings::route('/'),
        ];
    }

    /**
     * Global roles and the current organization's own roles.
     *
     * @return array<string, string>
     */
    private static function assignableRoles(): array
    {
        /** @var array<string, string> $roles */
        $roles = Role::query()
            ->where(fn ($query) => $query
                ->whereNull('organization_id')
                ->orWhere('organization_id', app(OrganizationContext::class)->id()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return $roles;
    }
}
