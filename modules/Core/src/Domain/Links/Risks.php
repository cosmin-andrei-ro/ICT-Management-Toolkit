<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Modules\Core\Domain\Models\User;

/**
 * Risks of the register that concern something of another module (an
 * application). Implemented by the Risk module; when it is disabled, none.
 */
interface Risks
{
    public function isAvailable(): bool;

    /**
     * Open risks concerning the thing, the highest current score first, if the viewer may read them.
     *
     * @param  string  $assetType  application, configuration_item, process or supplier
     * @return list<RiskSummary>
     */
    public function concerning(string $assetType, string $assetId, User $viewer): array;

    /**
     * How many risks concerning the thing have been assessed (they have a
     * current score), whoever asks: a count reveals no risk.
     *
     * @param  string  $assetType  application, configuration_item, process or supplier
     */
    public function assessedCount(string $assetType, string $assetId): int;
}
