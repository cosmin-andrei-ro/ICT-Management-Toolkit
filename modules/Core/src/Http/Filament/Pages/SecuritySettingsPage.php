<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Core\Application\Authentication\AccountLockout;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Models\SecuritySettings;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Which of the organization's roles must use two-factor authentication
 * (ADR 0057), and whether its passwords need mixed characters (ADR 0069). The lockout threshold and the breached-password check apply to
 * the whole installation and come from its configuration: they are shown here.
 *
 * @property-read Schema $form
 */
final class SecuritySettingsPage extends Page
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static ?string $slug = 'security';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function getNavigationGroup(): string
    {
        return __('core::directory.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::security_settings.title');
    }

    public function getTitle(): string
    {
        return __('core::security_settings.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('core.role.manage');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $settings = SecuritySettings::current();
        $this->form->fill([
            'mfa_required_roles' => array_values(array_filter(array_keys(self::roles()), fn (string $role): bool => $settings->requiresMfa($role))),
            'password_composition_required' => $settings->password_composition_required ?? false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $breached = config()->boolean('core.authentication.check_breached_passwords');

        return $schema->components([
            Text::make(__('core::security_settings.instance', [
                'count' => app(AccountLockout::class)->threshold(),
                'breached' => __($breached ? 'core::security_settings.refused' : 'core::security_settings.accepted'),
            ])),
            CheckboxList::make('mfa_required_roles')->label(__('core::security_settings.mfa_roles'))->helperText(__('core::security_settings.mfa_roles_help'))
                ->options(fn (): array => self::roles())
                ->in(fn (): array => array_keys(self::roles()))
                ->columns(2),
            Toggle::make('password_composition_required')->label(__('core::security_settings.composition'))->helperText(__('core::security_settings.composition_help')),
        ])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label(__('core::security_settings.save'))->submit('save')]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        /** @var array{mfa_required_roles?: list<string>, password_composition_required?: bool} $state */
        $state = $this->form->getState();
        $roles = array_values(array_intersect($state['mfa_required_roles'] ?? [], array_keys(self::roles())));

        $settings = SecuritySettings::current();
        $settings->forceFill([
            'organization_id' => app(OrganizationContext::class)->requireId(),
            'mfa_required_roles' => $roles,
            'password_composition_required' => $state['password_composition_required'] ?? false,
            'updated_by' => Auth::id(),
        ])->save();

        Notification::make()->success()->title(__('core::security_settings.saved'))->send();
    }

    /**
     * The roles of the organization by name, administrators left out: they always must.
     *
     * @return array<string, string>
     */
    private static function roles(): array
    {
        $organizationId = app(OrganizationContext::class)->id();

        return Role::query()
            ->where(fn (Builder $query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId))
            ->where('name', '!=', DefaultRole::Administrator->value)
            ->orderBy('name')
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name))
            ->mapWithKeys(fn (string $name): array => [$name => Str::headline($name)])
            ->all();
    }
}
