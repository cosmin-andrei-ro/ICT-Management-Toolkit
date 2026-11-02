<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Risks;

use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Core\Domain\Risks\RiskSignal;

/**
 * What the documentation in force says about an application that suggests
 * risks (CLAUDE.md §9, initial risks): personal or payment card data,
 * internet exposure without multifactor authentication, certificates
 * expiring, components past their end of support.
 */
final class ApplicationRiskSignals
{
    private const array PERSONAL = ['personal', 'special_personal', 'employee', 'medical'];

    private const array SENSITIVE = ['special_personal', 'medical', 'payment_card'];

    /**
     * @return list<RiskSignal>
     */
    public function __invoke(): array
    {
        $signals = [];

        foreach (Sheet::query()->with('application')->where('status', SheetStatus::Approved->value)->limit(1000)->get() as $sheet) {
            $application = $sheet->application;

            if ($application->deleted_at !== null) {
                continue;
            }

            $asset = [['type' => 'application', 'id' => $application->id]];
            $names = ['application' => $application->name];
            $url = fn (int $chapter): string => route('applications.sheets.show', [$sheet, 'chapter' => $chapter]);
            $source = (string) __('applications::risk_signals.source');

            $categories = $sheet->section('2_1')['categories'] ?? [];
            $categories = is_array($categories) ? array_keys(array_filter($categories)) : [];

            if (array_intersect($categories, [...self::PERSONAL, 'payment_card']) !== []) {
                $sensitive = array_intersect($categories, self::SENSITIVE) !== [];
                $signals[] = new RiskSignal(
                    'applications.data:'.$application->id, $source,
                    (string) __('applications::risk_signals.data.title', $names),
                    (string) __($sensitive ? 'applications::risk_signals.data.sensitive' : 'applications::risk_signals.data.description', $names),
                    'compliance', $asset, ['Data theft or leakage', 'Unauthorized access'], [],
                    null, $sensitive ? 5 : 4, $url(2), $application->business_owner_id,
                );
            }

            $exposure = $sheet->section('1_3')['exposure'] ?? [];
            $mfa = $sheet->section('3_1')['mfa'] ?? null;

            if (is_array($exposure) && in_array('internet', $exposure, true) && $mfa !== 'yes') {
                $signals[] = new RiskSignal(
                    'applications.no_mfa:'.$application->id, $source,
                    (string) __('applications::risk_signals.no_mfa.title', $names),
                    (string) __('applications::risk_signals.no_mfa.description', $names),
                    'information_security', $asset, ['Unauthorized access', 'Phishing and social engineering'], ['Weak authentication or no MFA', 'Service exposed to the internet'],
                    4, 4, $url(3), $application->technical_owner_id,
                );
            }

            $expiring = array_filter(self::rows($sheet, '8_1', 'certificates'), fn (array $row): bool => is_string($row['expires_on'] ?? null) && $row['expires_on'] <= today()->addDays(30)->toDateString());

            if ($expiring !== []) {
                $signals[] = new RiskSignal(
                    'applications.certificates:'.$application->id, $source,
                    (string) __('applications::risk_signals.certificates.title', $names),
                    (string) __('applications::risk_signals.certificates.description', [...$names, 'certificates' => implode(', ', array_map(fn (array $row): string => is_string($row['name'] ?? null) ? $row['name'] : '', $expiring))]),
                    'availability', $asset, ['Software failure'], ['Expired or unmanaged certificates'],
                    4, 3, $url(8), $application->technical_owner_id,
                );
            }

            $unsupported = array_filter(self::rows($sheet, '6_6', 'libraries'), fn (array $row): bool => is_string($row['support_until'] ?? null) && $row['support_until'] < today()->toDateString());

            if ($unsupported !== []) {
                $signals[] = new RiskSignal(
                    'applications.unsupported:'.$application->id, $source,
                    (string) __('applications::risk_signals.unsupported.title', $names),
                    (string) __('applications::risk_signals.unsupported.description', [...$names, 'components' => implode(', ', array_map(fn (array $row): string => is_string($row['name'] ?? null) ? $row['name'] : '', $unsupported))]),
                    'information_security', $asset, ['Ransomware and other malware', 'Software failure'], ['Unsupported software', 'Missing security updates'],
                    3, 4, $url(6), $application->technical_owner_id,
                );
            }
        }

        return $signals;
    }

    /**
     * @return list<array<mixed>>
     */
    private static function rows(Sheet $sheet, string $section, string $field): array
    {
        $rows = $sheet->section($section)[$field] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, is_array(...))) : [];
    }
}
