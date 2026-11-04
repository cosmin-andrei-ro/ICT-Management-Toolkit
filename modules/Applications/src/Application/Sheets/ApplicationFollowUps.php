<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Sheets;

use Illuminate\Database\ConnectionInterface;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\SoftwareLicences;
use Modules\Core\Domain\Models\User;

/**
 * What the approved documentation suggests doing elsewhere (ADR 0076, CLAUDE.md §9).
 * A person confirms each one: nothing is created by itself, because a process or a
 * licence made from guesses would show up in reports as if it were true. Each
 * proposal disappears once done, or when someone says it does not apply.
 */
final readonly class ApplicationFollowUps
{
    private const string TABLE = 'applications_followup_dismissals';

    public function __construct(
        private ConnectionInterface $db,
        private BusinessProcesses $processes,
        private SoftwareLicences $licences,
        private AuditTrail $trail,
    ) {}

    /**
     * @return list<array{key: string, label: string, url: ?string}>
     */
    public function for(Application $application, ?Sheet $inForce, User $viewer): array
    {
        if ($inForce === null) {
            return [];
        }

        $proposals = [];

        if ($this->processes->isAvailable() && array_filter((array) ($inForce->section('10_1')['processes'] ?? []), is_string(...)) === []) {
            $proposals[] = [
                'key' => 'process',
                'label' => (string) __('applications::applications.followups.process', ['name' => $application->name]),
                'url' => $this->processes->newProcessUrl($application->name, $viewer),
            ];
        }

        foreach ($this->commercialLibraries($inForce) as $name) {
            if ($this->licences->isAvailable() && ! $this->licences->isRecorded($name)) {
                $proposals[] = [
                    'key' => 'licence:'.sha1(mb_strtolower($name)),
                    'label' => (string) __('applications::applications.followups.licence', ['name' => $name]),
                    'url' => $this->licences->newLicenceUrl($name, $viewer),
                ];
            }
        }

        $dismissed = $this->db->table(self::TABLE)->where('application_id', $application->id)->pluck('key')->all();

        return array_values(array_filter($proposals, fn (array $proposal): bool => ! in_array($proposal['key'], $dismissed, true)));
    }

    public function dismiss(Application $application, string $key, User $actor): void
    {
        $this->db->table(self::TABLE)->insertOrIgnore([
            'application_id' => $application->id,
            'key' => mb_substr($key, 0, 80),
            'dismissed_by' => $actor->id,
            'created_at' => now(),
        ]);
        $this->trail->record(AuditEvent::Updated, $application, null, ['followup_dismissed' => mb_substr($key, 0, 80)], $actor->id);
    }

    /**
     * Only commercial software: open-source libraries need no licence records.
     *
     * @return list<string>
     */
    private function commercialLibraries(Sheet $sheet): array
    {
        $names = [];

        foreach ((array) ($sheet->section('6_6')['libraries'] ?? []) as $library) {
            if (is_array($library) && ($library['license'] ?? null) === 'commercial' && is_string($library['name'] ?? null) && trim($library['name']) !== '') {
                $names[mb_strtolower(trim($library['name']))] = trim($library['name']);
            }
        }

        return array_values($names);
    }
}
