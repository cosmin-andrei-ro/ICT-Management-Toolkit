<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Reminders;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Modules\Applications\Application\Notifications\CertificateExpiring;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;

/**
 * Daily, across organizations: emails the technical owner and the deputy of
 * each application whose documentation in force lists a certificate (8.1)
 * expiring soon or already expired, once per certificate and expiry date.
 */
final readonly class SendCertificateReminders
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private Config $config,
    ) {}

    /**
     * @return int Certificates announced.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $days = $this->config->get('applications.sheets.certificate_reminder_days');
        $horizon = $today->addDays(is_int($days) && $days > 0 ? $days : 30)->toDateString();
        $sent = 0;

        Sheet::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('status', SheetStatus::Approved->value)
            ->whereRaw("jsonb_array_length(coalesce(content->'8_1'->'certificates', '[]'::jsonb)) > 0")
            ->orderBy('id')
            ->each(function (Sheet $sheet) use ($horizon, &$sent): void {
                $this->context->runAs($sheet->organization_id, function () use ($sheet, $horizon, &$sent): void {
                    $application = Application::query()->find($sheet->application_id);

                    if ($application === null) {
                        return;
                    }

                    $certificates = $sheet->section('8_1')['certificates'] ?? [];

                    foreach (is_array($certificates) ? $certificates : [] as $certificate) {
                        $name = is_array($certificate) && is_string($certificate['name'] ?? null) ? mb_substr($certificate['name'], 0, 255) : null;
                        $expires = is_array($certificate) && is_string($certificate['expires_on'] ?? null) ? $certificate['expires_on'] : null;

                        if ($name === null || $expires === null || $expires > $horizon) {
                            continue;
                        }

                        // Recorded first: a concurrent run sends nothing twice.
                        $recorded = $this->db->table('applications_certificate_reminders')->insertOrIgnore([
                            'id' => (string) Str::uuid7(),
                            'organization_id' => $sheet->organization_id,
                            'application_id' => $application->id,
                            'certificate' => $name,
                            'expires_on' => $expires,
                            'sent_at' => now(),
                        ]);

                        if ($recorded === 0) {
                            continue;
                        }

                        $notification = new CertificateExpiring($application->name, $name, $expires, route('applications.sheets.show', [$sheet, 'chapter' => 8]));
                        User::query()->whereKey(array_values(array_filter([$application->technical_owner_id, $application->deputy_id])))->get()
                            ->filter(fn (User $user): bool => $user->canSignIn())
                            ->each(fn (User $user) => $user->notify($notification));
                        $sent++;
                    }
                });
            });

        return $sent;
    }
}
