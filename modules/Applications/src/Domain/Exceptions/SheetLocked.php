<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Domain\Exceptions;

use RuntimeException;

/**
 * The version is not in the state the change needs (submitted, in force, superseded).
 */
final class SheetLocked extends RuntimeException {}
