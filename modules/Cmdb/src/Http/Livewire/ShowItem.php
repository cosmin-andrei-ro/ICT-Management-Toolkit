<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Cmdb\Application\Actions\ManageRelationships;
use Modules\Cmdb\Application\Impact\ImpactAnalysis;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\CiLink;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Domain\Models\Relationship;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;

/**
 * One item: what it is, how it relates to others, and what a failure would affect.
 *
 * @property-read ConfigurationItem $item Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowItem extends Component
{
    /** Nodes drawn around the item, so large estates stay readable. */
    private const int GRAPH_LIMIT = 60;

    #[Locked]
    public string $itemId = '';

    public string $relationshipType = 'depends_on';

    /** "outgoing": this item <type> other; "incoming": other <type> this item. */
    public string $direction = 'outgoing';

    public string $otherSearch = '';

    public string $otherId = '';

    public function mount(ConfigurationItem $item): void
    {
        $this->authorize('view', $item);
        $this->itemId = $item->id;
    }

    #[Computed]
    public function item(): ConfigurationItem
    {
        return ConfigurationItem::query()
            ->with(['type', 'owner', 'team', 'outgoing.target.type', 'incoming.source.type'])
            ->findOrFail($this->itemId);
    }

    public function addRelationship(ManageRelationships $relationships): void
    {
        $this->authorize('update', $this->item);
        $this->validate([
            'relationshipType' => ['required', Rule::enum(RelationshipType::class)],
            'direction' => ['required', Rule::in(['outgoing', 'incoming'])],
            'otherId' => ['required', 'uuid'],
        ]);

        // Scoped lookup: an item of another organization is not found.
        $other = ConfigurationItem::query()->findOrFail($this->otherId);
        [$source, $target] = $this->direction === 'outgoing' ? [$this->item, $other] : [$other, $this->item];

        try {
            $relationships->add($source, RelationshipType::from($this->relationshipType), $target);
        } catch (InvalidArgumentException) {
            $this->addError('otherId', __('cmdb::relationships.ui.invalid'));

            return;
        }

        $this->reset('otherSearch', 'otherId');
        unset($this->item);
    }

    public function removeRelationship(string $relationshipId, ManageRelationships $relationships): void
    {
        $this->authorize('update', $this->item);
        $relationship = Relationship::query()
            ->whereKey($relationshipId)
            ->where(fn ($query) => $query->where('source_id', $this->itemId)->orWhere('target_id', $this->itemId))
            ->firstOrFail();

        $relationships->remove($relationship);
        unset($this->item);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->item);
        $this->item->delete();
        $this->redirectRoute('cmdb.items.index');
    }

    public function render(ImpactAnalysis $impact, LinkedRecords $linkedRecords): View
    {
        $item = $this->item;
        $search = trim($this->otherSearch);

        return view('cmdb::livewire.show', [
            'item' => $item,
            'fields' => $item->type->schema()->fields,
            'affected' => $impact->affectedBy([$item->id]),
            'dependencies' => $impact->dependenciesOf([$item->id]),
            'relationshipTypes' => RelationshipType::cases(),
            'candidates' => mb_strlen($search) >= 2
                ? ConfigurationItem::query()->with('type')->search(mb_substr($search, 0, 200))->whereKeyNot($item->id)->orderBy('name')->limit(10)->get()
                : collect(),
            'graph' => $this->graph($item),
            'linkedRecords' => $this->linkedRecords($item, $linkedRecords),
        ])->title($item->name);
    }

    /**
     * Records of other modules linked to this item (tickets…), as the viewer may see them.
     *
     * @return list<LinkedRecord>
     */
    private function linkedRecords(ConfigurationItem $item, LinkedRecords $linkedRecords): array
    {
        $viewer = auth()->user();

        if (! $viewer instanceof User) {
            return [];
        }

        $records = [];

        foreach (CiLink::query()->where('configuration_item_id', $item->id)->latest('created_at')->limit(200)->get()->groupBy('linkable_type') as $type => $links) {
            $ids = array_values(array_filter($links->pluck('linkable_id')->all(), is_string(...)));
            $records = [...$records, ...$linkedRecords->resolve((string) $type, $ids, $viewer)];
        }

        return $records;
    }

    /**
     * The item and its neighbours two steps away, for the drawing.
     *
     * @return array{nodes: list<array<string, string|bool>>, edges: list<array<string, string>>}
     */
    private function graph(ConfigurationItem $item): array
    {
        $ids = collect([$item->id]);
        $edges = new Collection;

        for ($step = 0; $step < 2; $step++) {
            $found = Relationship::query()
                ->where(fn ($query) => $query->whereIn('source_id', $ids)->orWhereIn('target_id', $ids))
                ->limit(self::GRAPH_LIMIT * 2)
                ->get();
            $edges = $edges->merge($found)->unique('id');
            $ids = $ids->merge($found->pluck('source_id'))->merge($found->pluck('target_id'))->unique()->take(self::GRAPH_LIMIT);
        }

        $nodes = ConfigurationItem::query()->with('type')->whereKey($ids->all())->get()->keyBy('id');

        return [
            'nodes' => array_values($nodes->map(fn (ConfigurationItem $node): array => [
                'id' => $node->id,
                'label' => $node->name,
                'type' => $node->type->name,
                'criticality' => $node->criticality->value,
                'current' => $node->id === $item->id,
                'url' => route('cmdb.items.show', $node),
            ])->all()),
            'edges' => array_values($edges
                ->filter(fn (Relationship $edge): bool => $nodes->has($edge->source_id) && $nodes->has($edge->target_id))
                ->map(fn (Relationship $edge): array => ['id' => $edge->id, 'source' => $edge->source_id, 'target' => $edge->target_id, 'label' => $edge->type->label()])
                ->all()),
        ];
    }
}
