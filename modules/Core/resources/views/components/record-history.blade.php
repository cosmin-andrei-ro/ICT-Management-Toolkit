@props(['record', 'ability' => 'update', 'target' => null])
{{-- Those who may change the record, and the audit log readers, see its history. --}}
@if (\Modules\Core\Http\Livewire\Audit\RecordHistory::visible($ability, $target ?? $record))
    @livewire(\Modules\Core\Http\Livewire\Audit\RecordHistory::class, ['record' => $record, 'ability' => $ability, 'target' => $target], key('history-'.$record->getKey()))
@endif
