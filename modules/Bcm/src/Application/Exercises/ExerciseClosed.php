<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Exercises;

use RuntimeException;

/**
 * The exercise is completed or cancelled (or not in the state the change needs).
 */
final class ExerciseClosed extends RuntimeException {}
