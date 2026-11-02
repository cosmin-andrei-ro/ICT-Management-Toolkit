<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Risks;

/**
 * Something a module knows that suggests a risk (an internet-facing
 * application without MFA, a recovery gap, a contract ending…). The risk
 * register turns signals into suggestions for its risk managers; nothing
 * becomes a risk without them.
 */
final readonly class RiskSignal
{
    /**
     * @param  string  $key  Stable per situation (e.g. "applications.no_mfa:<application id>"): the same situation keeps the same suggestion.
     * @param  string  $category  information_security, availability, compliance, supplier, operational or project.
     * @param  list<array{type: string, id: string}>  $assets  application, configuration_item, process or supplier.
     * @param  list<string>  $threats  Names in the risk catalog's starter set.
     * @param  list<string>  $vulnerabilities  Names in the risk catalog's starter set.
     */
    public function __construct(
        public string $key,
        public string $source,
        public string $title,
        public string $description,
        public string $category,
        public array $assets = [],
        public array $threats = [],
        public array $vulnerabilities = [],
        public ?int $likelihood = null,
        public ?int $impact = null,
        public ?string $evidenceUrl = null,
        public ?string $ownerId = null,
    ) {}
}
