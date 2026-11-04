@props(['record', 'ability' => 'update'])
@can('viewAny', \Modules\Core\Domain\Models\Tag::class)
    @livewire(\Modules\Core\Http\Livewire\Tags\RecordTags::class, ['record' => $record, 'ability' => $ability], key('tags-'.$record->getKey()))
@endcan
