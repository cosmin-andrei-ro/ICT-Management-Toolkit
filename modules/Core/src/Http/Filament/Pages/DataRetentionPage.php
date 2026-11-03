<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Domain\Models\RetentionSettings;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Retention\DataRetention;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The organization's retention periods (ADR 0060), one per policy the
 * modules registered; empty keeps that data forever.
 *
 * @property-read Schema $form
 */
final class DataRetentionPage extends Page
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static ?string $slug = 'data-retention';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxXMark;

    public static function getNavigationGroup(): string
    {
        return __('core::directory.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::retention.title');
    }

    public function getTitle(): string
    {
        return __('core::retention.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('core.personal_data.manage');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $settings = RetentionSettings::current();
        $state = [];

        foreach (app(DataRetention::class)->policies() as $key => $policy) {
            $state[self::field($key)] = $settings->years($key, $policy['default']);
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $fields = [Text::make(__('core::retention.intro'))];

        foreach (app(DataRetention::class)->policies() as $key => $policy) {
            $fields[] = TextInput::make(self::field($key))->label(__($policy['label']))
                ->helperText(__($policy['help']).' '.__('core::retention.default', ['years' => $policy['default']]))
                ->suffix(__('core::retention.years'))->integer()->minValue(1)->maxValue(50)->nullable();
        }

        return $schema->components($fields)->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label(__('core::retention.save'))->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        /** @var array<string, int|string|null> $state */
        $state = $this->form->getState();
        $periods = [];

        foreach (array_keys(app(DataRetention::class)->policies()) as $key) {
            $value = $state[self::field($key)] ?? null;
            $periods[$key] = is_numeric($value) ? (int) $value : null;
        }

        $settings = RetentionSettings::current();
        $settings->forceFill([
            'organization_id' => app(OrganizationContext::class)->requireId(),
            'periods' => $periods,
            'updated_by' => Auth::id(),
        ])->save();

        Notification::make()->success()->title(__('core::retention.saved'))->send();
    }

    /**
     * Dots would nest the form state: "ticketing.closed_tickets" → "ticketing__closed_tickets".
     */
    private static function field(string $policy): string
    {
        return str_replace('.', '__', $policy);
    }
}
