<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Filament\Pages;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Domain\Models\RiskSettings;

/**
 * The organization's scoring method: the five likelihood and impact levels,
 * the bands of likelihood × impact, the approval threshold and the review interval.
 *
 * @property-read Schema $form
 */
final class RiskSettingsPage extends Page
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static ?string $slug = 'risk/method';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    public static function getNavigationGroup(): string
    {
        return __('risk::settings.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('risk::settings.page.title');
    }

    public function getTitle(): string
    {
        return __('risk::settings.page.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('risk.settings.manage');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $settings = RiskSettings::current();
        $this->form->fill([
            'likelihood_levels' => $settings->likelihood_levels,
            'impact_levels' => $settings->impact_levels,
            'medium_from' => $settings->medium_from,
            'high_from' => $settings->high_from,
            'critical_from' => $settings->critical_from,
            'approval_from' => $settings->approval_from,
            'review_months' => $settings->review_months,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $levels = fn (string $axis): array => array_merge(...array_map(fn (int $index): array => [
            TextInput::make("{$axis}_levels.{$index}.label")->label(__('risk::settings.page.level_label', ['level' => $index + 1]))->required()->maxLength(64),
            TextInput::make("{$axis}_levels.{$index}.description")->label(__('risk::settings.page.level_description', ['level' => $index + 1]))->maxLength(500),
        ], range(0, 4)));
        $score = fn (string $field): TextInput => TextInput::make($field)->label(__("risk::settings.page.{$field}"))->integer()->required()->minValue(2)->maxValue(25);

        return $schema->components([
            Section::make(__('risk::settings.page.likelihood'))->schema($levels('likelihood'))->columns(2),
            Section::make(__('risk::settings.page.impact'))->schema($levels('impact'))->columns(2),
            Section::make(__('risk::settings.page.bands'))->description(__('risk::settings.page.bands_help'))->schema([
                $score('medium_from'),
                $score('high_from')->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    if (self::number($value) <= self::number($get('medium_from'))) {
                        $fail(__('risk::settings.page.order'));
                    }
                }]),
                $score('critical_from')->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    if (self::number($value) <= self::number($get('high_from'))) {
                        $fail(__('risk::settings.page.order'));
                    }
                }]),
                TextInput::make('approval_from')->label(__('risk::settings.page.approval_from'))->helperText(__('risk::settings.page.approval_help'))->integer()->required()->minValue(1)->maxValue(25),
                TextInput::make('review_months')->label(__('risk::settings.page.review_months'))->integer()->required()->minValue(1)->maxValue(60),
            ])->columns(3),
        ])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label(__('risk::settings.page.save'))->submit('save')]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = $this->form->getState();
        $levels = fn (mixed $value): array => array_map(
            fn (mixed $level): array => ['label' => self::text(is_array($level) ? ($level['label'] ?? null) : null), 'description' => self::text(is_array($level) ? ($level['description'] ?? null) : null)],
            array_values(is_array($value) ? $value : []),
        );

        $settings = RiskSettings::query()->first() ?? new RiskSettings;
        $settings->forceFill([
            'organization_id' => app(OrganizationContext::class)->requireId(),
            'likelihood_levels' => $levels($state['likelihood_levels'] ?? []),
            'impact_levels' => $levels($state['impact_levels'] ?? []),
            'medium_from' => self::number($state['medium_from'] ?? null),
            'high_from' => self::number($state['high_from'] ?? null),
            'critical_from' => self::number($state['critical_from'] ?? null),
            'approval_from' => self::number($state['approval_from'] ?? null),
            'review_months' => self::number($state['review_months'] ?? null),
            'updated_by' => Auth::id(),
        ])->save();

        Notification::make()->success()->title(__('risk::settings.page.saved'))->send();
    }

    private static function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
