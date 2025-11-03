@php
    $record = $getRecord();
    $recordId = $record->getKey();
    $modelType = class_basename($record);
@endphp
<livewire:tables.mail-service-cell :record="$record" :service-type="$serviceType" :open-mode="$openMode" :modal-width="$modalWidth"
    :button-size="$buttonSize" :show-message="$showMessage"
    wire:key="ms-cell-{{ $recordId }}-{{ $getName() }}-{{ $openMode }}-{{ $modelType }}" />
