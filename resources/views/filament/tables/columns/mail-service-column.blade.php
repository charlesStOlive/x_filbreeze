@php
    $record = $getRecord();
    $state = $getState();
@endphp

<div>
    @livewire('mail-service-cell', [
        'record' => $state['record'],
        'serviceType' => $state['serviceType'],
        'openMode' => $state['openMode'],
        'modalWidth' => $state['modalWidth'],
        'buttonSize' => $state['buttonSize'],
        'showMessage' => $state['showMessage'],
    ])
</div>
