<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Authorization;

/**
 * Global roles shipped with the application. Their permissions are owned by code
 * and reset on every sync; organizations needing other sets create their own roles.
 */
enum DefaultRole: string
{
    case Administrator = 'administrator';
    case TeamLead = 'team-lead';
    case Agent = 'agent';
    case CabMember = 'cab-member';
    case AssetManager = 'asset-manager';
    case ContinuityCoordinator = 'continuity-coordinator';
    case ApplicationManager = 'application-manager';
    case SecurityOfficer = 'security-officer';
    case RiskManager = 'risk-manager';
    case ComplianceManager = 'compliance-manager';
    case Auditor = 'auditor';
    case ProjectManager = 'project-manager';
    case Requester = 'requester';
}
