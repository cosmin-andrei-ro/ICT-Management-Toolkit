<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Domain\Sheet;

/**
 * The template's data categories and the elements of each (2.1 Data
 * Categories). Part of the template, so fixed in code; anything else goes in
 * "Other". Labels: applications::data.
 */
final class DataCategories
{
    /** Categories that make the data personal under the GDPR. */
    public const array PERSONAL = ['personal', 'special_personal', 'employee', 'medical'];

    /**
     * @return array<string, list<string>> Element keys by category key.
     */
    public static function all(): array
    {
        return [
            'personal' => ['name', 'contact', 'national_id', 'birth_date', 'address', 'online_identifiers', 'photo_video'],
            'special_personal' => ['health', 'biometric', 'genetic', 'ethnic_origin', 'religious_beliefs', 'political_opinions', 'trade_union', 'sexual_orientation'],
            'employee' => ['contracts', 'payroll', 'performance', 'attendance', 'recruitment', 'background_checks'],
            'payment_card' => ['pan', 'cardholder_name', 'expiry_date', 'cvv', 'pin', 'stripe_chip'],
            'financial' => ['account_numbers', 'balances', 'credit', 'statements', 'investments'],
            'transactions' => ['payments', 'orders', 'clearing', 'binding_contracts', 'invoices_issued'],
            'accounting' => ['general_ledger', 'invoices', 'tax_returns', 'audit_records', 'budgets'],
            'legal' => ['contracts', 'litigation', 'regulatory_filings', 'legal_opinions', 'aml_kyc'],
            'medical' => ['patient_records', 'diagnoses', 'prescriptions', 'lab_results', 'insurance_claims'],
            'customer' => ['crm', 'pricing', 'offers', 'sales_pipeline', 'marketing_lists'],
            'intellectual_property' => ['designs', 'formulas', 'source_code', 'research', 'patents'],
            'operational' => ['production_parameters', 'sensor_scada', 'quality_records', 'maintenance_logs'],
            'logistics' => ['shipments', 'inventory', 'supplier_data', 'tracking'],
            'telecom' => ['call_records', 'traffic', 'geolocation', 'subscribers'],
            'security' => ['passwords', 'keys_certificates', 'tokens', 'security_logs', 'mfa'],
            'it' => ['hostnames', 'configuration', 'system_logs', 'asset_inventory', 'network_topology', 'vulnerabilities'],
            'communications' => ['emails', 'chat', 'call_recordings', 'support_tickets', 'meeting_recordings'],
            'analytics' => ['cookies', 'device_identifiers', 'clickstream', 'usage_statistics', 'behavioural_profiles'],
            'education' => ['student_records', 'grades', 'enrolment', 'attendance', 'guardians'],
            'public_sector' => ['citizen_records', 'tax_ids', 'permits', 'case_files', 'classified'],
            'public' => ['published_content', 'marketing_material', 'public_catalogues'],
        ];
    }
}
