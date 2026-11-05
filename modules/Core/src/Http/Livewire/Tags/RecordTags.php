<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Tags;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Application\Tags\ManageTags;
use Modules\Core\Domain\Concerns\Taggable;
use Modules\Core\Domain\Models\Tag;

/**
 * The tags of a record, on its page. Everything is checked on the record at each
 * action: the type and id come from the browser, however locked.
 */
final class RecordTags extends Component
{
    #[Locked]
    public string $type = '';

    #[Locked]
    public string $recordId = '';

    /** The ability on the record that lets someone change it: "update", or "work" for tickets. */
    #[Locked]
    public string $ability = 'update';

    public string $name = '';

    public function mount(Model&Taggable $record, string $ability = 'update'): void
    {
        $this->type = $record->getMorphClass();
        $key = $record->getKey();
        $this->recordId = is_string($key) ? $key : '';
        $this->ability = $ability;
        $this->record();
    }

    public function add(ManageTags $tags): void
    {
        $record = $this->record();
        $this->authorize($this->ability, $record);
        $this->resetErrorBag();

        try {
            $tags->add($record, $this->name);
        } catch (InvalidArgumentException $failure) {
            $this->addError('name', __('core::tags.errors.'.$failure->getMessage(), ['max' => ManageTags::MAX_PER_RECORD]));

            return;
        }

        $this->name = '';
    }

    public function remove(ManageTags $tags, string $tagId): void
    {
        $record = $this->record();
        $this->authorize($this->ability, $record);
        $tags->remove($record, $tagId);
    }

    public function render(): View
    {
        $record = $this->record();

        return view('core::livewire.tags.record-tags', [
            'tags' => $record->tags()->orderBy('name')->get(),
            'canChange' => Gate::allows($this->ability, $record),
            'suggestions' => Tag::query()->orderBy('name')->limit(200)->pluck('name'),
        ]);
    }

    private function record(): Model&Taggable
    {
        abort_unless(Gate::allows('viewAny', Tag::class), 403);
        $class = Relation::getMorphedModel($this->type);
        abort_unless(is_string($class) && is_subclass_of($class, Taggable::class), 404);
        $record = $class::query()->findOrFail($this->recordId);
        // A custom role can work on a ticket without the right to read every ticket.
        abort_unless(Gate::any(['view', $this->ability], $record), 403);

        return $record;
    }
}
