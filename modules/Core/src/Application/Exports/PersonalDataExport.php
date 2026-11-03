<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Exports;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Application\Audit\AuditChanges;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Models\Attachment;
use Modules\Core\Domain\Models\Comment;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalData;
use Modules\Core\Http\Livewire\Audit\AuditLogIndex;

/**
 * Everything the organization holds about one person (GDPR Art. 15 and 20,
 * ADR 0058): their account, every record each module names them in, what
 * they wrote and uploaded, and their activity in the audit log. A workbook,
 * so it is both readable and machine-readable.
 */
final readonly class PersonalDataExport implements ExportSource
{
    public const string TYPE = 'core.personal_data';

    private const int MAX_ACTIVITY = 50000;

    public function __construct(private PersonalData $personalData) {}

    public function sheets(Export $export): array
    {
        $person = $this->person($export);

        if ($person === null) {
            return [new ExportSheet(__('core::personal_data.export.profile'), [__('core::personal_data.export.field'), __('core::personal_data.export.value')], [])];
        }

        $timezone = config()->string('app.display_timezone');
        $sheets = [
            new ExportSheet(__('core::personal_data.export.profile'), [__('core::personal_data.export.field'), __('core::personal_data.export.value')], $this->profile($person)),
        ];
        $names = [];

        foreach ($this->personalData->collectors() as $label => $collector) {
            $rows = [];

            foreach ($collector($person) as $entry) {
                $rows[] = [$entry->record, $entry->reference, $entry->title, $entry->role, self::date($entry->date, $timezone), $entry->details];
            }

            if ($rows !== []) {
                $name = mb_substr(__($label), 0, 28);
                $names[$name] = ($names[$name] ?? 0) + 1;
                $sheets[] = new ExportSheet($names[$name] > 1 ? $name.' '.$names[$name] : $name, [
                    __('core::personal_data.export.record'), __('core::personal_data.export.reference'), __('core::personal_data.export.title'),
                    __('core::personal_data.export.role'), __('core::personal_data.export.date'), __('core::personal_data.export.details'),
                ], $rows);
            }
        }

        $sheets[] = new ExportSheet(__('core::personal_data.export.comments'), [__('core::audit.fields.when'), __('core::audit.fields.record_type'), __('core::personal_data.export.internal'), __('core::personal_data.export.text')], $this->comments($person, $timezone));
        $sheets[] = new ExportSheet(__('core::personal_data.export.files'), [__('core::audit.fields.when'), __('core::audit.fields.record_type'), __('core::personal_data.export.file'), __('core::personal_data.export.size')], $this->files($person, $timezone));
        $sheets[] = new ExportSheet(__('core::personal_data.export.activity'), [
            __('core::audit.fields.when'), __('core::audit.fields.event'), __('core::audit.fields.record_type'), __('core::audit.fields.record'),
            __('core::audit.fields.field'), __('core::audit.fields.old'), __('core::audit.fields.new'), __('core::audit.fields.ip_address'),
        ], $this->activity($person, $export->organization_id, $timezone));

        return $sheets;
    }

    public function filename(Export $export): string
    {
        return 'personal-data-'.$export->created_at?->format('Y-m-d');
    }

    /**
     * The person, if they belong to the export's organization: each organization answers for its own data.
     */
    private function person(Export $export): ?User
    {
        $id = $export->parameters['person_id'] ?? null;

        return is_string($id)
            ? User::query()->whereKey($id)->whereHas('organizations', fn (Builder $query) => $query->whereKey($export->organization_id))->first()
            : null;
    }

    /**
     * @return list<list<string|null>>
     */
    private function profile(User $person): array
    {
        $timezone = config()->string('app.display_timezone');
        $date = fn (?DateTimeInterface $value): ?string => self::date($value, $timezone);
        $yes = fn (bool $value): string => $value ? __('core::audit.yes') : __('core::audit.no');
        // The roles relation is scoped to the current organization (permission teams): the export's.
        $roles = $person->roles()->pluck('name')->filter(fn (mixed $name): bool => is_string($name))->implode(', ');

        return [
            [__('core::users.name'), $person->name],
            [__('core::users.email'), $person->email],
            [__('core::users.source'), __('core::users.sources.'.$person->auth_source->value)],
            [__('core::personal_data.export.directory_username'), $person->directory_username],
            [__('core::users.roles'), $roles],
            [__('core::users.active'), $yes($person->is_active)],
            [__('core::personal_data.export.created'), $date($person->created_at)],
            [__('core::users.last_login'), $date($person->last_login_at)],
            [__('core::users.mfa'), $yes($person->two_factor_confirmed_at !== null)],
            [__('core::users.locked'), $date($person->locked_at)],
        ];
    }

    /**
     * @return Generator<list<string|bool|null>>
     */
    private function comments(User $person, string $timezone): Generator
    {
        foreach (Comment::query()->where('author_id', $person->id)->orderBy('created_at')->lazy(500) as $comment) {
            yield [$comment->created_at?->timezone($timezone)->format('Y-m-d H:i'), AuditLogIndex::typeLabel($comment->commentable_type), $comment->is_internal, $comment->body];
        }
    }

    /**
     * @return Generator<list<string|int|null>>
     */
    private function files(User $person, string $timezone): Generator
    {
        foreach (Attachment::query()->where('uploaded_by', $person->id)->orderBy('created_at')->lazy(500) as $attachment) {
            yield [$attachment->created_at?->timezone($timezone)->format('Y-m-d H:i'), AuditLogIndex::typeLabel($attachment->attachable_type), $attachment->original_name, $attachment->size];
        }
    }

    /**
     * What the person did, and what was done to their account, in this organization.
     *
     * @return Generator<list<string|null>>
     */
    private function activity(User $person, string $organizationId, string $timezone): Generator
    {
        $entries = AuditLog::query()
            ->where(fn (Builder $query) => $query->where('organization_id', $organizationId)->orWhereNull('organization_id'))
            ->where(fn (Builder $query) => $query->where('user_id', $person->id)->orWhere(fn (Builder $query) => $query->where('auditable_type', 'core.user')->where('auditable_id', $person->id)))
            ->orderBy('created_at')->orderBy('id')
            ->limit(self::MAX_ACTIVITY)
            ->cursor();

        foreach ($entries->chunk(500) as $batch) {
            $changes = AuditChanges::for($batch->all());

            foreach ($batch as $entry) {
                $base = [$entry->created_at->timezone($timezone)->format('Y-m-d H:i:s'), $entry->event->label(), AuditLogIndex::typeLabel($entry->auditable_type), $entry->auditable_id];
                $rows = $changes->rows($entry);

                foreach ($rows === [] ? [['field' => null, 'old' => null, 'new' => null]] : $rows as $row) {
                    yield [...$base, $row['field'], $row['old'], $row['new'], $entry->ip_address];
                }
            }
        }
    }

    private static function date(?DateTimeInterface $value, string $timezone): ?string
    {
        return $value !== null ? CarbonImmutable::instance($value)->timezone($timezone)->format('Y-m-d H:i') : null;
    }
}
