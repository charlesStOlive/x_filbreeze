<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Group
    |--------------------------------------------------------------------------
    |
    | The navigation group where the permission resources will be displayed
    | in the Filament admin panel.
    |
    */
    'navigation_group' => 'Administration',

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Configure which resources should be available. You can disable resources
    | if you want to create your own implementation.
    |
    */
    'resources' => [
        'permission' => true,
        'role' => true,
        'user' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Commands
    |--------------------------------------------------------------------------
    |
    | Configure which commands should be available. All commands are enabled
    | by default but you can disable them if needed.
    |
    */
    'commands' => [
        'sync' => true,
        'reset' => true,
        'generate_seeder' => true,
        'add_resource' => true,
        'publish_user_resources' => true,
        'make_user_enhanced' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Actions
    |--------------------------------------------------------------------------
    |
    | Default actions that will be created when adding permissions for a
    | new resource using the add-resource command.
    |
    */
    'default_actions' => ['viewany', 'view', 'create', 'edit', 'delete'],

    /*
    |--------------------------------------------------------------------------
    | Auto Super Admin Role
    |--------------------------------------------------------------------------
    |
    | Automatically create and assign all permissions to a 'Super Admin' role.
    | This happens during sync operations.
    |
    */
    'auto_admin_role' => true,

    /*
    | Le nom de ce rôle : le même dans toutes les applications et tous les plugins (voir Support\SuperAdmin).
    | Ne l'écrivez jamais en dur : SuperAdmin::role(), SuperAdmin::is($user), SuperAdmin::isRole($role).
    */
    'super_admin_role' => 'Super Admin',

    /*
    |--------------------------------------------------------------------------
    | Protected Permissions
    |--------------------------------------------------------------------------
    |
    | Permissions that start with these prefixes will be excluded from
    | cleanup operations to prevent accidental deletion.
    |
    */
    'protected_prefixes' => ['s_'],

    /*
    |--------------------------------------------------------------------------
    | Filament Scan Paths
    |--------------------------------------------------------------------------
    |
    | Paths that will be scanned for Filament resources when syncing
    | permissions. Relative to app_path().
    |
    */
    'scan_paths' => [
        'Filament/Resources',
        'Filament/Clusters',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | fallback_policy : une Resource Filament sans policy et sans contrôle d'accès à elle (pas de
    | HasFilamentAuthorization) reçoit une PermissionPolicy qui vérifie `{préfixe}.{action}`.
    | Sans elle, Filament autorise tout utilisateur connecté.
    |
    | excluded_resources : Resources laissées sans contrôle de permission (ni policy de repli, ni
    | permissions créées par permissions:sync), par ex. la documentation d'un panel d'aide que tout
    | utilisateur du panel doit lire.
    |
    | declared_permissions : les pages et widgets d'un package qui déclarent `$requiresPermission`
    | reçoivent une ability Gate au nom de leur classe (voir Support\DeclaredPermissions).
    |
    | role_scoped_permissions : des permissions « par rôle », une par rôle existant, que l'application
    | déclare elle-même (ex. 'aiinteraction.viewrole' → aiinteraction.viewrole.editeur…), quand la Resource
    | qui pourrait les déclarer (`$roleScopedPermissions`) n'est dans aucun panel. Voir
    | Support\RoleScopedPermissions.
    |
    | Une application pas encore adaptée peut mettre fallback_policy et declared_permissions à false :
    | elle garde l'ancien comportement (tout ce qui n'a pas de contrôle à lui reste ouvert).
    |
    */
    'authorization' => [
        'fallback_policy' => true,
        'declared_permissions' => true,
        'role_scoped_permissions' => [],
        // Le centre d'aide : tout utilisateur qui entre dans son panel (panels.knowledge-base.access) peut le lire.
        'excluded_resources' => [
            Guava\FilamentKnowledgeBase\Filament\Resources\DocumentationResource::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for user-related features like timezone and locale.
    |
    */
    'user_settings' => [
        'timezone' => [
            'enabled' => true,
            'default' => 'Europe/Paris',
            'use_for_datetime_picker' => true,
        ],
        'locale' => [
            'enabled' => true,
            'default' => 'fr_FR',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Service Cache
    |--------------------------------------------------------------------------
    |
    | Enable caching for permission checks to improve performance.
    | Cache will be cleared automatically when permissions are modified.
    |
    */
    'cache' => [
        'enabled' => false,
        'ttl' => 3600, // 1 hour
        'key_prefix' => 'permission_manager',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Features
    |--------------------------------------------------------------------------
    |
    | Optional integrations for API tokens and two-factor authentication.
    |
    */
    'passport' => [
        'enabled' => false,
        'token_expiration_days' => 365,
    ],

    'fortify' => [
        'enabled' => false,
        'features' => [
            'two_factor' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Context
    |--------------------------------------------------------------------------
    |
    | - unified: existing behavior, same permission check for web and api.
    | - separated: api requests will first try "api.{permission}" then fallback.
    |
    */
    'permission_context' => 'unified',

    'api' => [
        'guard' => 'api',
        'token_prefix' => 'fpm_',
        'middleware' => [
            'validate' => 'fpm.api.validate',
            'permission' => 'fpm.api.permission',
        ],
    ],
];
