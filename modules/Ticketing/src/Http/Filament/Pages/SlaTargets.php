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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Actions\SaveSlaTargets;
use Modules\Ticketing\Domain\Enums\Priority;
use Modules\Ticketing\Domain\Sla\SlaTarget;

/**
 * Response and resolution targets per priority, in working minutes.
 *
 * @property-read Schema $form
 */
final class SlaTargets extends Page
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static ?string $slug = 'ticketing/sla-targets';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function getNavigationGroup(): string
    {
        return __('ticketing::admin.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('ticketing::admin.sla_targets.title');
    }

    public function getTitle(): string
    {
        return __('ticketing::admin.sla_targets.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('ticketing.settings.manage');
    }

    public function mount(SaveSlaTargets $targets): void
    {
        abort_unless(self::canAccess(), 403);

        $data = [];
        foreach ($targets->current(app(OrganizationContext::class)->requireId()) as $priority => $minutes) {
            $data["{$priority}_response"] = $minutes['response'];
            $data["{$priority}_resolution"] = $minutes['resolution'];
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (Priority::cases() as $priority) {
            $sections[] = Section::make($priority->label())->schema([
                Grid::make(2)->schema([
                    TextInput::make("{$priority->value}_response")
                        ->label(__('ticketing::admin.sla_targets.response'))
                        ->integer()->minValue(1)->maxValue(525600)->required()
                        ->suffix(__('ticketing::admin.sla_targets.minutes')),
                    TextInput::make("{$priority->value}_resolution")
                        ->label(__('ticketing::admin.sla_targets.resolution'))
                        ->integer()->required()->maxValue(525600)
                        ->minValue(fn (Get $get): int => max(1, filter_var($get("{$priority->value}_response"), FILTER_VALIDATE_INT) ?: 1))
                        ->suffix(__('ticketing::admin.sla_targets.minutes')),
                ]),
            ]);
        }

        return $schema->components([
            Section::make()->description(__('ticketing::admin.sla_targets.help')),
            ...$sections,
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

    public function save(SaveSlaTargets $saveTargets): void
    {
        abort_unless(self::canAccess(), 403);

        /** @var array<string, int|string> $state */
        $state = $this->form->getState();

        $targets = [];
        foreach (Priority::cases() as $priority) {
            $targets[$priority->value] = new SlaTarget(
                filter_var($state["{$priority->value}_response"], FILTER_VALIDATE_INT) ?: 0,
                filter_var($state["{$priority->value}_resolution"], FILTER_VALIDATE_INT) ?: 0,
            );
        }

        $organization = Organization::query()->findOrFail(app(OrganizationContext::class)->requireId());
        $saveTargets->handle($organization, $targets);

        Notification::make()->success()->title(__('ticketing::admin.saved'))->send();
    }
}
