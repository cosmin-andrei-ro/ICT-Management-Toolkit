<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Enums;

/**
 * A record of the toolkit linked, or a document uploaded. Mirrored by audit_evidence_kind_check.
 */
enum EvidenceKind: string
{
    case Link = 'link';
    case File = 'file';
}
