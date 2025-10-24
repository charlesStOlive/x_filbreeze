<?php

return [
    /*
    |--------------------------------------------------------------------------
    | State Analysis Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the State Analysis Service integrated with
    | Filament State Fusion Enhanced
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
        'enabled' => env('STATE_FUSION_ENHANCED_CACHE_ENABLED', true),

        // Cache duration in seconds (default: 1 hour)
        'duration' => env('STATE_FUSION_ENHANCED_CACHE_DURATION', 3600),

        // Cache key prefix
        'prefix' => env('STATE_FUSION_ENHANCED_CACHE_PREFIX', 'state_fusion_enhanced'),

        // Cache store to use (null = default)
        'store' => env('STATE_FUSION_ENHANCED_CACHE_STORE', null),
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
        // Directories to scan for models (relative to app path)
        'directories' => [
            'Models',
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
            'diagram_type' => 'flowchart',
            'direction' => 'LR',
            'include_styles' => true,
            'include_comments' => true,
            'include_tooltips' => false,
            'max_line_length' => 30,
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

        'markdown' => [
            'include_mermaid_diagram' => true,
            'include_metadata' => true,
            'include_state_details' => true,
            'include_transition_details' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for Filament components and features
    |
    */

    'filament' => [
        // Enable Mermaid diagram infolist component
        'enable_mermaid_component' => true,

        // Enable enhanced StateFusion actions
        'enable_enhanced_actions' => true,

        // Default theme for Mermaid diagrams in Filament
        'mermaid_theme' => 'default', // default, dark, forest, neutral
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
        'enabled' => env('STATE_FUSION_ENHANCED_API_ENABLED', true),

        // API route prefix
        'prefix' => 'api/state-fusion-enhanced',

        // Default format for API responses
        'default_format' => 'json',

        // Rate limiting (requests per minute)
        'rate_limit' => env('STATE_FUSION_ENHANCED_API_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Commands Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Artisan commands
    |
    */

    'commands' => [
        // Enable the state analysis command
        'enable_analyze_command' => true,

        // Command signature for state analysis
        'analyze_signature' => 'state-fusion:analyze',
    ],
];