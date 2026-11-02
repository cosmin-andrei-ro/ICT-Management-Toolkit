<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Bia\Domain\Models\BiaSettings;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The unacceptable impact level and the review interval of the organization.
 *
 * @property-read Schema $form
 */
final class BiaSettingsPage extends Page
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static ?string $slug = 'bia/settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    public static function getNavigationGroup(): string
    {
        return __('bia::settings.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('bia::settings.page.title');
    }

    public function getTitle(): string
    {
        return __('bia::settings.page.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('bia.settings.manage');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $settings = BiaSettings::current();
        $this->form->fill(['unacceptable_level' => $settings->unacceptable_level, 'review_months' => $settings->review_months]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('unacceptable_level')->label(__('bia::settings.page.level'))->helperText(__('bia::settings.page.level_help'))
                ->integer()->required()->minValue(2)->maxValue(5),
            TextInput::make('review_months')->label(__('bia::settings.page.review_months'))->helperText(__('bia::settings.page.review_help'))
                ->integer()->required()->minValue(1)->maxValue(60),
        ])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label(__('bia::settings.page.save'))->submit('save')]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        /** @var array{unacceptable_level: int|string, review_months: int|string} $state */
        $state = $this->form->getState();

        $settings = BiaSettings::current();
        $settings->forceFill([
            'organization_id' => app(OrganizationContext::class)->requireId(),
            'unacceptable_level' => (int) $state['unacceptable_level'],
            'review_months' => (int) $state['review_months'],
            'updated_by' => Auth::id(),
        ])->save();

        Notification::make()->success()->title(__('bia::settings.page.saved'))->send();
    }
}
