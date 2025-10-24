<?php

return [
    /*
    |--------------------------------------------------------------------------
    | State Analysis Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the State Analysis Service
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Control caching behavior for state analysis results
    |
    */

    'cache' => [
        // Enable/disable caching
        'enabled' => env('STATE_ANALYSIS_CACHE_ENABLED', true),

        // Cache duration in seconds (default: 1 hour)
        'duration' => env('STATE_ANALYSIS_CACHE_DURATION', 3600),

        // Cache key prefix
        'prefix' => env('STATE_ANALYSIS_CACHE_PREFIX', 'state_analysis'),

        // Cache store to use (null = default)
        'store' => env('STATE_ANALYSIS_CACHE_STORE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Discovery Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how models are discovered and analyzed
    |
    */

    'models' => [
        // Directories to scan for models
        'directories' => [
            app_path('Models'),
        ],

        // Namespace prefix for models
        'namespace' => 'App\\Models\\',

        // File extensions to consider
        'extensions' => ['php'],

        // Exclude patterns (glob patterns)
        'exclude' => [
            '**/.*', // Hidden files
            '**/Test*', // Test files
        ],

        // Only include models that use these traits
        'required_traits' => [
            // 'Spatie\\ModelStates\\HasStates',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatter Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default options for built-in formatters
    |
    */

    'formatters' => [
        'mermaid' => [
            'diagram_type' => 'stateDiagram-v2',
            'direction' => 'TD',
            'include_styles' => true,
            'include_tooltips' => true,
            'max_label_length' => 50,
            'state_prefix' => '',
            'transition_labels' => true,
        ],

        'json' => [
            'format' => 'mermaid', // mermaid, raw, simple
            'pretty_print' => true,
            'include_metadata' => true,
        ],

        'array' => [
            'format' => 'structured', // structured, flat, legacy
            'include_metadata' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Formatters
    |--------------------------------------------------------------------------
    |
    | Register custom formatter classes here. They must implement
    | App\Contracts\StateFormatterInterface
    |
    */

    'custom_formatters' => [
        // Example:
        // App\Services\Formatters\CustomFormatter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for API endpoints
    |
    */

    'api' => [
        // Enable API endpoints
        'enabled' => env('STATE_ANALYSIS_API_ENABLED', true),

        // API route prefix
        'prefix' => 'api/state-analysis',

        // Default format for API responses
        'default_format' => 'json',

        // Rate limiting (requests per minute)
        'rate_limit' => env('STATE_ANALYSIS_API_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for the state analysis service
    |
    */

    'security' => [
        // Allowed model classes (empty = all allowed)
        'allowed_models' => [
            // 'App\\Models\\Invoice',
            // 'App\\Models\\Order',
        ],

        // Blocked model classes
        'blocked_models' => [
            // 'App\\Models\\User',
            // 'App\\Models\\Admin',
        ],

        // Maximum number of models to analyze in batch
        'max_batch_size' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Performance tuning options
    |
    */

    'performance' => [
        // Maximum execution time for analysis (seconds)
        'max_execution_time' => 30,

        // Memory limit for analysis (MB)
        'memory_limit' => 512,

        // Enable detailed profiling
        'profiling' => env('STATE_ANALYSIS_PROFILING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    |
    | Debugging and logging options
    |
    */

    'debug' => [
        // Enable debug mode
        'enabled' => env('STATE_ANALYSIS_DEBUG', false),

        // Log analysis operations
        'log_operations' => env('STATE_ANALYSIS_LOG_OPERATIONS', false),

        // Log channel to use
        'log_channel' => env('STATE_ANALYSIS_LOG_CHANNEL', 'single'),

        // Include stack traces in errors
        'include_traces' => env('STATE_ANALYSIS_INCLUDE_TRACES', false),
    ],
];