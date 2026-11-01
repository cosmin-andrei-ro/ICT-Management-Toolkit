<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Application\Impact;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Walks the relationship graph in the database (recursive CTE). "Affected" answers
 * "if these fail, what else stops working?"; "dependencies" answers "what do
 * these need to work?". Cycles are cut, depth is limited, and retired or
 * deleted items are skipped.
 */
final readonly class ImpactAnalysis
{
    public const int MAX_DEPTH = 10;

    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
    ) {}

    /**
     * @param  list<string>  $itemIds
     * @return Collection<int, ImpactedItem> Nearest first.
     */
    public function affectedBy(array $itemIds): Collection
    {
        return $this->walk($itemIds, downstream: true);
    }

    /**
     * @param  list<string>  $itemIds
     * @return Collection<int, ImpactedItem> Nearest first.
     */
    public function dependenciesOf(array $itemIds): Collection
    {
        return $this->walk($itemIds, downstream: false);
    }

    /**
     * @param  list<string>  $itemIds
     * @return Collection<int, ImpactedItem>
     */
    private function walk(array $itemIds, bool $downstream): Collection
    {
        if ($itemIds === []) {
            return new Collection;
        }

        // Each relationship as an edge "cause → affected", following its type.
        $causeIsTarget = [];
        $causeIsSource = [];

        foreach (RelationshipType::cases() as $type) {
            match ($type->affectedSide()) {
                'source' => $causeIsTarget[] = $type->value,
                'target' => $causeIsSource[] = $type->value,
                null => null,
            };
        }

        [$from, $to] = $downstream ? ['cause', 'affected'] : ['affected', 'cause'];
        $placeholders = implode(', ', array_fill(0, count($itemIds), '?'));
        $targetTypes = implode(', ', array_fill(0, count($causeIsTarget), '?'));
        $sourceTypes = implode(', ', array_fill(0, count($causeIsSource), '?'));

        $sql = <<<SQL
            WITH RECURSIVE edges AS (
                SELECT target_id AS cause, source_id AS affected FROM cmdb_relationships WHERE organization_id = ? AND type IN ({$targetTypes})
                UNION ALL
                SELECT source_id AS cause, target_id AS affected FROM cmdb_relationships WHERE organization_id = ? AND type IN ({$sourceTypes})
            ),
            live AS (
                SELECT id FROM cmdb_configuration_items WHERE organization_id = ? AND deleted_at IS NULL AND status <> 'retired'
            ),
            walk (item_id, depth) AS (
                SELECT e.{$to}, 1 FROM edges e WHERE e.{$from} IN ({$placeholders}) AND e.{$to} IN (SELECT id FROM live)
                UNION ALL
                SELECT e.{$to}, w.depth + 1 FROM walk w JOIN edges e ON e.{$from} = w.item_id
                WHERE w.depth < ? AND e.{$to} IN (SELECT id FROM live)
            ) CYCLE item_id SET is_cycle USING path
            SELECT item_id, min(depth) AS depth FROM walk
            WHERE NOT is_cycle AND item_id NOT IN ({$placeholders})
            GROUP BY item_id
            ORDER BY min(depth)
            SQL;

        $organizationId = $this->context->requireId();
        $rows = $this->db->select($sql, [
            $organizationId, ...$causeIsTarget,
            $organizationId, ...$causeIsSource,
            $organizationId,
            ...$itemIds,
            self::MAX_DEPTH,
            ...$itemIds,
        ]);

        $depths = [];

        foreach ($rows as $row) {
            if (is_object($row) && is_string($row->item_id ?? null) && is_numeric($row->depth ?? null)) {
                $depths[$row->item_id] = (int) $row->depth;
            }
        }

        $items = ConfigurationItem::query()->with('type')->whereKey(array_keys($depths))->get()->keyBy('id');

        $impacted = [];

        foreach ($depths as $id => $depth) {
            $item = $items->get($id);

            if ($item instanceof ConfigurationItem) {
                $impacted[] = new ImpactedItem($item, $depth);
            }
        }

        // Nearest first; at the same distance, the most critical first.
        usort($impacted, fn (ImpactedItem $a, ImpactedItem $b): int => [$a->depth, -$a->item->criticality->weight(), $a->item->name] <=> [$b->depth, -$b->item->criticality->weight(), $b->item->name]);

        return new Collection($impacted);
    }
}
