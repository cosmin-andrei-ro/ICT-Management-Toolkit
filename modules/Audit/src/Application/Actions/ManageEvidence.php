<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Audit\Domain\Enums\EvidenceKind;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\ControlTest;
use Modules\Audit\Domain\Models\Evidence;
use Modules\Core\Application\Collaboration\StoreAttachment;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;

/**
 * Evidence of a control: a record of the toolkit the actor may see, or an
 * uploaded document (Core's attachment rules). Audited on the evidence.
 */
final readonly class ManageEvidence
{
    public function __construct(
        private ConnectionInterface $db,
        private LinkedRecords $records,
        private StoreAttachment $attachments,
    ) {}

    /**
     * @throws InvalidArgumentException for a record the actor may not see, or already linked
     */
    public function link(Control $control, string $type, string $id, ?string $description, User $actor): Evidence
    {
        // Resolved through its module as the actor sees it: no linking what one cannot open.
        $record = $this->records->resolve($type, [$id], $actor)[0] ?? throw new InvalidArgumentException('Unknown record.');

        if ($control->evidence()->where('linked_type', $type)->where('linked_id', $record->id)->exists()) {
            throw new InvalidArgumentException('Already linked.');
        }

        return $this->create($control, [
            'kind' => EvidenceKind::Link,
            'title' => mb_substr($record->title, 0, 255),
            'description' => self::text($description),
            'linked_type' => $type,
            'linked_id' => $record->id,
        ]);
    }

    /**
     * @throws ValidationException keyed to "file" for a file Core refuses
     */
    public function upload(Control $control, UploadedFile $file, ?string $title, ?string $description, ?CarbonImmutable $validUntil, User $actor, ?ControlTest $test = null): Evidence
    {
        $this->attachments->assertAcceptable($file);

        return $this->db->transaction(function () use ($control, $file, $title, $description, $validUntil, $actor, $test): Evidence {
            $evidence = $this->create($control, [
                'kind' => EvidenceKind::File,
                'test_id' => $test?->id,
                'title' => mb_substr(self::text($title) ?? StoreAttachment::cleanName($file->getClientOriginalName()), 0, 255),
                'description' => self::text($description),
                'valid_until' => $validUntil,
            ]);
            $this->attachments->handle($evidence, $actor, $file, false);

            return $evidence;
        });
    }

    public function remove(Evidence $evidence): void
    {
        // Soft deleted, its document kept: the history still shows what was presented.
        $evidence->delete();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function create(Control $control, array $values): Evidence
    {
        $evidence = new Evidence;
        $evidence->forceFill([...$values, 'organization_id' => $control->organization_id, 'control_id' => $control->id])->save();

        return $evidence;
    }

    private static function text(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : '';

        return $value !== '' ? $value : null;
    }
}
