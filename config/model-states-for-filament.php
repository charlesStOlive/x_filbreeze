<?php

declare(strict_types=1);

return [
    /*
     * Specifies the driver used for handling model states. The default driver is 'spatie',
     * but this can be overridden by setting the 'MODEL_STATES_DRIVER' environment variable.
     */
    'driver' => env('MODEL_STATES_DRIVER', 'spatie'),

    'spatie' => [
        /*
         * Defines the strategy used to sort states. The default strategy sorts states alphabetically.
         * Custom sorting strategies should implement the `StateSortingStrategy` interface.
         */
        'state_sorting_strategy' => App\Filament\ModelStates\AlphabeticallyStateSorting::class,
    ],
];
