<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Domain\Enums;

/**
 * How items relate. Read as "source <type> target": an application *runs on* a
 * server, a service *depends on* a database, a node *is part of* a cluster.
 */
enum RelationshipType: string
{
    case DependsOn = 'depends_on';
    case RunsOn = 'runs_on';
    case Uses = 'uses';
    case PartOf = 'part_of';
    case ConnectsTo = 'connects_to';

    /**
     * As seen from the source: "runs on".
     */
    public function label(): string
    {
        $label = __('cmdb::relationships.types.'.$this->value.'.forward');

        return is_string($label) ? $label : $this->value;
    }

    /**
     * As seen from the target: "hosts".
     */
    public function inverseLabel(): string
    {
        $label = __('cmdb::relationships.types.'.$this->value.'.inverse');

        return is_string($label) ? $label : $this->value;
    }

    /**
     * Which side is affected when the other fails. Network links (connects to)
     * carry no impact: following them would mark the whole network as affected.
     *
     * @return 'source'|'target'|null
     */
    public function affectedSide(): ?string
    {
        return match ($this) {
            // The source needs the target: the target failing affects the source.
            self::DependsOn, self::RunsOn, self::Uses => 'source',
            // A part failing degrades the whole it belongs to.
            self::PartOf => 'target',
            self::ConnectsTo => null,
        };
    }
}
