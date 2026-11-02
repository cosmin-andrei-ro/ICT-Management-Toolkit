<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Actions;

use RuntimeException;

/**
 * The page was saved by someone else since it was opened for editing.
 */
final class PageChangedMeanwhile extends RuntimeException {}
