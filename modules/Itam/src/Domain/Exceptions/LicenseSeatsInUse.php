<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Domain\Exceptions;

use DomainException;

/**
 * A licence with seats in use cannot switch between people and devices.
 */
final class LicenseSeatsInUse extends DomainException {}
