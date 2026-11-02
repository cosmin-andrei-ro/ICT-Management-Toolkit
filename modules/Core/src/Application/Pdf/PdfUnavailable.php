<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Pdf;

use RuntimeException;

/**
 * No PDF service is configured, or it did not answer.
 */
final class PdfUnavailable extends RuntimeException {}
