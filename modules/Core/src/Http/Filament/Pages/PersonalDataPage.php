<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Exports\PersonalDataExport;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Application\PersonalData\AnonymizePerson;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Requests from people about their personal data (GDPR Art. 15 and 20,
 * ADR 0058): an administrator prepares the export of everything the
 * organization holds about one of its members, and hands it over.
 *
 * @property-read Schema $form
 */
final class PersonalDataPage extends Page
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static ?string $slug = 'personal-data';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    public static function getNavigationGroup(): string
    {
        return __('core::directory.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::personal_data.title');
    }

    public function getTitle(): string
    {
        return __('core::personal_data.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('core.personal_data.manage');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Text::make(__('core::personal_data.intro')),
            self::personSelect('person_id')->helperText(__('core::personal_data.person_help')),
        ])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->footer([
                    // An action, not a submit button: the confirmation says how to hand the data over.
                    Actions::make([Action::make('prepare')->label(__('core::personal_data.prepare'))
                        ->requiresConfirmation()->modalDescription(__('core::personal_data.prepare_help'))
                        ->action(fn (RequestExport $request) => $this->prepare($request))]),
                ]),
            Section::make(__('core::personal_data.recent'))->schema(fn (): array => $this->exportComponents()),
            Section::make(__('core::personal_data.anonymize.title'))->schema([
                Text::make(__('core::personal_data.anonymize.intro')),
                Actions::make([$this->anonymizeAction()]),
            ]),
        ]);
    }

    private function anonymizeAction(): Action
    {
        return Action::make('anonymize')->label(__('core::personal_data.anonymize.action'))->color('danger')
            ->modalHeading(__('core::personal_data.anonymize.title'))->modalDescription(__('core::personal_data.anonymize.description'))
            ->modalSubmitActionLabel(__('core::personal_data.anonymize.submit'))
            ->schema([
                self::personSelect('person_id'),
                TextInput::make('confirmation')->label(__('core::personal_data.anonymize.confirmation'))->required()->maxLength(320),
                TextInput::make('code')->label(__('core::personal_data.anonymize.code'))->required()->maxLength(10)->autocomplete('one-time-code'),
            ])
            ->action(function (array $data, Action $action, AnonymizePerson $anonymize): void {
                abort_unless(self::canAccess(), 403);
                $administrator = Auth::user();
                $person = is_string($data['person_id'] ?? null) ? self::members()->whereKey($data['person_id'])->first() : null;

                if (! $administrator instanceof User || $person === null) {
                    return;
                }

                try {
                    $anonymize->handle($person, is_string($data['confirmation'] ?? null) ? $data['confirmation'] : '', is_string($data['code'] ?? null) ? $data['code'] : '', $administrator);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title($exception->validator->errors()->first())->send();
                    $action->halt();
                }

                Notification::make()->success()->title(__('core::personal_data.anonymize.done'))->send();
            });
    }

    public function prepare(RequestExport $request): void
    {
        abort_unless(self::canAccess(), 403);

        /** @var array{person_id: string} $state */
        $state = $this->form->getState();
        $actor = Auth::user();

        if ($actor instanceof User) {
            // RequestExport checks the permission and records the export in the audit trail.
            $request->handle($actor, PersonalDataExport::TYPE, ExportFormat::Xlsx, ['person_id' => $state['person_id']]);
            $this->form->fill();
            Notification::make()->success()->title(__('core::personal_data.prepared'))->send();
        }
    }

    /**
     * The administrator's recent exports, with their download links; refreshed while one is being prepared.
     *
     * @return list<Component>
     */
    private function exportComponents(): array
    {
        $exports = Export::query()->where('user_id', Auth::id())->where('type', PersonalDataExport::TYPE)->latest()->limit(10)->get();

        if ($exports->isEmpty()) {
            return [Text::make(__('core::personal_data.none'))];
        }

        $ids = $exports->map(fn (Export $export): mixed => $export->parameters['person_id'] ?? null)->filter(fn (mixed $id): bool => is_string($id))->unique()->values()->all();
        $people = User::query()->whereKey($ids)->get(['id', 'name'])->mapWithKeys(fn (User $user): array => [$user->id => $user->name])->all();
        $timezone = config()->string('app.display_timezone');
        $components = [];

        foreach ($exports as $export) {
            $personId = $export->parameters['person_id'] ?? null;
            $label = __('core::personal_data.for', ['name' => is_string($personId) && isset($people[$personId]) ? $people[$personId] : __('core::personal_data.unknown_person')])
                .' · '.$export->created_at?->timezone($timezone)->format('Y-m-d H:i');

            $components[] = Flex::make($export->isDownloadable()
                ? [
                    Text::make($label),
                    Actions::make([Action::make('download_'.$export->id)->label(__('core::exports.download'))->link()->url(route('core.exports.download', $export))])->grow(false),
                    Text::make(__('core::exports.expires', ['date' => $export->expires_at?->timezone($timezone)->format('Y-m-d H:i')]))->color('gray')->grow(false),
                ]
                : [Text::make($label), Text::make($export->status->label())->color('gray')->grow(false)]);
        }

        if ($exports->contains(fn (Export $export): bool => $export->status === ExportStatus::Pending)) {
            $components[] = View::make('core::filament.poll');
        }

        return $components;
    }

    private static function personSelect(string $name): Select
    {
        return Select::make($name)->label(__('core::personal_data.person'))
            ->searchable()->required()
            ->getSearchResultsUsing(fn (string $search): array => self::members()->where(fn (Builder $query) => $query->where('name', 'ilike', '%'.self::escape($search).'%')->orWhere('email', 'ilike', '%'.self::escape($search).'%'))
                ->orderBy('name')->limit(20)->get(['id', 'name', 'email'])->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' <'.$user->email.'>'])->all())
            ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value) ? self::members()->whereKey($value)->first(['name', 'email'])?->name : null)
            ->in(fn (): Collection => self::members()->pluck('id'));
    }

    /**
     * @return Builder<User>
     */
    private static function members(): Builder
    {
        $organizationId = app(OrganizationContext::class)->requireId();

        return User::query()->whereHas('organizations', fn (Builder $query) => $query->whereKey($organizationId));
    }

    private static function escape(string $search): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 100));
    }
}
