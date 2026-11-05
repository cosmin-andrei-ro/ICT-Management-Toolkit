@props(['record', 'ability' => 'update'])
@livewire(\Modules\Core\Http\Livewire\CustomFields\RecordCustomFields::class, ['record' => $record, 'ability' => $ability], key('custom-fields-'.$record->getKey()))
