<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Bia\Domain\Models\ImpactCategory;
use Modules\Bia\Domain\Models\TimeHorizon;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The impact categories and time horizons every organization starts with
 * (owner's decision), editable afterwards. Created on first use, each set only
 * once: an organization that deleted them does not get them back.
 */
final readonly class ProvisionDefaultMatrix
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
    ) {}

    public function handle(): void
    {
        $organizationId = $this->context->requireId();

        $this->db->transaction(function () use ($organizationId): void {
            if (! ImpactCategory::query()->withTrashed()->exists()) {
                foreach (self::categories() as $order => [$name, $description, $levels]) {
                    $category = new ImpactCategory;
                    $category->forceFill(['organization_id' => $organizationId, 'name' => $name, 'description' => $description, 'levels' => $levels, 'sort_order' => $order])->save();
                }
            }

            if (! TimeHorizon::query()->withTrashed()->exists()) {
                foreach (self::horizons() as $label => $minutes) {
                    $horizon = new TimeHorizon;
                    $horizon->forceFill(['organization_id' => $organizationId, 'label' => $label, 'minutes' => $minutes])->save();
                }
            }
        });
    }

    /**
     * @return list<array{string, string, list<string>}>
     */
    public static function categories(): array
    {
        return [
            ['Financial', 'Lost revenue, extra costs, penalties.', ['No noticeable loss', 'Small loss, absorbed by the budget', 'Significant loss, needs management attention', 'Major loss, affects the year\'s results', 'Loss threatening the organization\'s viability']],
            ['Legal and regulatory', 'Breaches of laws, regulations and contracts.', ['None', 'Minor breach, nothing to report', 'Breach to report to an authority; a warning is likely', 'Fines or sanctions are likely', 'Licence withdrawn or criminal liability']],
            ['Reputation', 'How customers, partners and the public see the organization.', ['Not noticed', 'Noticed internally or by a few customers', 'Social or local media', 'National media', 'Lasting loss of trust']],
            ['Operations', 'The organization\'s ability to keep working.', ['No effect', 'Work is slower', 'Backlog; some services degraded', 'Core services stopped', 'The organization cannot operate']],
            ['Customers and partners', 'Commitments to customers and partners.', ['None', 'A few complaints', 'Many complaints; service levels at risk', 'Contracts breached; customers lost', 'Key customers or partners lost']],
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function horizons(): array
    {
        return ['4 hours' => 240, '1 day' => 1440, '3 days' => 4320, '1 week' => 10080, '2 weeks' => 20160, '1 month' => 43200];
    }
}
