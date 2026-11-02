<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| The template's data categories and elements (2.1 Data Categories).
*/

return [
    'category' => 'Data category',
    'elements_processed' => 'Data elements processed',
    'other_placeholder' => 'Describe the data type and its data elements',
    'categories' => [
        'personal' => 'Personal Data (GDPR)',
        'special_personal' => 'Special Category Personal Data (GDPR Art. 9)',
        'employee' => 'Employee / HR Data',
        'payment_card' => 'Payment Card Data (PCI DSS)',
        'financial' => 'Financial & Banking Data',
        'transactions' => 'Business Transactions',
        'accounting' => 'Accounting & Tax Data',
        'legal' => 'Legal & Compliance Data',
        'medical' => 'Medical / Health Records (HIPAA)',
        'customer' => 'Customer & Commercial Data',
        'intellectual_property' => 'Product & Intellectual Property',
        'operational' => 'Manufacturing / Operational (OT) Data',
        'logistics' => 'Logistics & Supply Chain Data',
        'telecom' => 'Telecom & Location Data',
        'security' => 'Authentication & Security Data',
        'it' => 'IT & Infrastructure Data',
        'communications' => 'Communications & Support Data',
        'analytics' => 'Web & Usage Analytics',
        'education' => 'Education / Student Data',
        'public_sector' => 'Government / Public Sector Data',
        'public' => 'Public Data',
        'other' => 'Other',
    ],
    'elements' => [
        'personal' => ['name' => 'Name', 'contact' => 'Contact details', 'national_id' => 'National ID / passport', 'birth_date' => 'Date of birth', 'address' => 'Address', 'online_identifiers' => 'IP address / online identifiers', 'photo_video' => 'Photo / video'],
        'special_personal' => ['health' => 'Health', 'biometric' => 'Biometric', 'genetic' => 'Genetic', 'ethnic_origin' => 'Racial / ethnic origin', 'religious_beliefs' => 'Religious beliefs', 'political_opinions' => 'Political opinions', 'trade_union' => 'Trade union membership', 'sexual_orientation' => 'Sexual orientation'],
        'employee' => ['contracts' => 'Contracts', 'payroll' => 'Payroll / salary', 'performance' => 'Performance evaluations', 'attendance' => 'Attendance / leave', 'recruitment' => 'Recruitment data', 'background_checks' => 'Background checks'],
        'payment_card' => ['pan' => 'PAN', 'cardholder_name' => 'Cardholder name', 'expiry_date' => 'Expiry date', 'cvv' => 'CVV / CVC', 'pin' => 'PIN', 'stripe_chip' => 'Magnetic stripe / chip data'],
        'financial' => ['account_numbers' => 'Account numbers / IBAN', 'balances' => 'Balances', 'credit' => 'Credit / loan data', 'statements' => 'Statements', 'investments' => 'Investment / securities data'],
        'transactions' => ['payments' => 'Payments / transfers', 'orders' => 'Orders', 'clearing' => 'Clearing & settlement', 'binding_contracts' => 'Binding contracts', 'invoices_issued' => 'Invoices issued'],
        'accounting' => ['general_ledger' => 'General ledger', 'invoices' => 'Invoices', 'tax_returns' => 'Tax returns', 'audit_records' => 'Audit records', 'budgets' => 'Budgets / forecasts'],
        'legal' => ['contracts' => 'Contracts', 'litigation' => 'Litigation files', 'regulatory_filings' => 'Regulatory filings', 'legal_opinions' => 'Legal opinions', 'aml_kyc' => 'AML / KYC records'],
        'medical' => ['patient_records' => 'Patient records', 'diagnoses' => 'Diagnoses', 'prescriptions' => 'Prescriptions', 'lab_results' => 'Lab results', 'insurance_claims' => 'Insurance claims'],
        'customer' => ['crm' => 'CRM records', 'pricing' => 'Pricing', 'offers' => 'Offers / quotes', 'sales_pipeline' => 'Sales pipeline', 'marketing_lists' => 'Marketing lists'],
        'intellectual_property' => ['designs' => 'Designs / drawings', 'formulas' => 'Formulas / recipes', 'source_code' => 'Source code', 'research' => 'R&D data', 'patents' => 'Patents / trade secrets'],
        'operational' => ['production_parameters' => 'Production parameters', 'sensor_scada' => 'Sensor / SCADA data', 'quality_records' => 'Quality records', 'maintenance_logs' => 'Maintenance logs'],
        'logistics' => ['shipments' => 'Shipments', 'inventory' => 'Inventory', 'supplier_data' => 'Supplier data', 'tracking' => 'Tracking / GPS'],
        'telecom' => ['call_records' => 'Call detail records', 'traffic' => 'Traffic / usage data', 'geolocation' => 'Geolocation', 'subscribers' => 'Subscriber data'],
        'security' => ['passwords' => 'Passwords / hashes', 'keys_certificates' => 'Keys / certificates', 'tokens' => 'Tokens / sessions', 'security_logs' => 'Security logs', 'mfa' => 'MFA data'],
        'it' => ['hostnames' => 'Hostnames / IP addresses', 'configuration' => 'System configuration', 'system_logs' => 'Application / system logs', 'asset_inventory' => 'Asset inventory (CMDB)', 'network_topology' => 'Network topology', 'vulnerabilities' => 'Vulnerability / scan results'],
        'communications' => ['emails' => 'E-mails', 'chat' => 'Chat / messaging', 'call_recordings' => 'Call recordings', 'support_tickets' => 'Support tickets', 'meeting_recordings' => 'Meeting recordings'],
        'analytics' => ['cookies' => 'Cookies', 'device_identifiers' => 'Device identifiers', 'clickstream' => 'Clickstream / browsing history', 'usage_statistics' => 'Usage statistics', 'behavioural_profiles' => 'Behavioural profiles'],
        'education' => ['student_records' => 'Student records', 'grades' => 'Grades / transcripts', 'enrolment' => 'Enrolment data', 'attendance' => 'Attendance', 'guardians' => 'Parent / guardian details'],
        'public_sector' => ['citizen_records' => 'Citizen records', 'tax_ids' => 'Tax / social security IDs', 'permits' => 'Permits / licences', 'case_files' => 'Case files', 'classified' => 'Classified / restricted information'],
        'public' => ['published_content' => 'Published content', 'marketing_material' => 'Marketing material', 'public_catalogues' => 'Public catalogues / prices'],
    ],
];
