<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Actions\SaveOlaTargets;
use Modules\Ticketing\Domain\Models\TeamOla;

/**
 * How long a ticket may stay with each team, in working minutes (ADR 0074).
 *
 * @property-read Schema $form
 */
final class OlaTargets extends Page
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static ?string $slug = 'ticketing/ola-targets';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function getNavigationGroup(): string
    {
        return __('ticketing::admin.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('ticketing::admin.ola_targets.title');
    }

    public function getTitle(): string
    {
        return __('ticketing::admin.ola_targets.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('ticketing.settings.manage');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->form->fill(TeamOla::query()->get()->mapWithKeys(fn (TeamOla $ola): array => [$ola->team_id => $ola->minutes])->all());
    }

    public function form(Schema $schema): Schema
    {
        $fields = Team::query()->orderBy('name')->get()->map(
            fn (Team $team): TextInput => TextInput::make($team->id)->label($team->name)
                ->integer()->minValue(1)->maxValue(525600)
                ->suffix(__('ticketing::admin.sla_targets.minutes')),
        )->all();

        return $schema->components([
            Section::make()->description(__('ticketing::admin.ola_targets.help'))->schema($fields),
        ])->statePath('data');
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

    public function save(SaveOlaTargets $saveTargets): void
    {
        abort_unless(self::canAccess(), 403);

        /** @var array<string, int|string|null> $state */
        $state = $this->form->getState();
        $targets = [];

        foreach (Team::query()->get() as $team) {
            $value = $state[$team->id] ?? null;
            $targets[$team->id] = $value === null || $value === '' ? null : (int) $value;
        }

        $saveTargets->handle($targets);
        Notification::make()->success()->title(__('ticketing::admin.saved'))->send();
    }
}
