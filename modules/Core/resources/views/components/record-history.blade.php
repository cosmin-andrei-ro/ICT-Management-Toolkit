@props(['record', 'canChange' => false])
{{-- The page knows who may change its record; the audit log readers see the history too. --}}
@if (\Modules\Core\Http\Livewire\Audit\RecordHistory::visible($canChange))
    @livewire(\Modules\Core\Http\Livewire\Audit\RecordHistory::class, ['record' => $record, 'canChange' => $canChange], key('history-'.$record->getKey()))
@endif
