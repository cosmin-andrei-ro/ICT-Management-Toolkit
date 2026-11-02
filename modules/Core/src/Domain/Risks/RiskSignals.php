<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Risks;

use Closure;
use Throwable;

/**
 * Modules register here what suggests risks in the current organization. The
 * Risk module reads them; a module that is disabled simply registers nothing.
 */
final class RiskSignals
{
    /** @var array<string, Closure(): list<RiskSignal>> */
    private array $providers = [];

    /**
     * @param  string  $source  The prefix of its signals' keys (the module's key, e.g. "applications").
     * @param  Closure(): list<RiskSignal>  $provider  Called within the organization's context.
     */
    public function register(string $source, Closure $provider): void
    {
        $this->providers[$source] = $provider;
    }

    /**
     * @return list<string>
     */
    public function sources(): array
    {
        return array_keys($this->providers);
    }

    /**
     * @return array{signals: list<RiskSignal>, failed: list<string>} A failing source is reported, not fatal.
     */
    public function collect(): array
    {
        $signals = [];
        $failed = [];

        foreach ($this->providers as $source => $provider) {
            try {
                foreach ($provider() as $signal) {
                    $signals[] = $signal;
                }
            } catch (Throwable $exception) {
                report($exception);
                $failed[] = $source;
            }
        }

        return ['signals' => $signals, 'failed' => $failed];
    }
}
