<?php

return [



    'navigation' => [
        'cluster' =>
        [
            'icon' => 'heroicon-o-envelope',
            'label' => 'Emails',
            'sort' => 1,
        ],
        'draft_users' =>
        [
            'icon' => 'heroicon-o-document-arrow-up',
            'label' => 'Utilisateurs email brouillons',
            'sort' => 1,
            'showInNavigation' => true,
        ],
        'in_users' =>
        [
            'icon' => 'heroicon-o-document-arrow-down',
            'label' => 'Utilisateurs email entrant',
            'sort' => 2,
            'showInNavigation' => true,
        ],
    ],


    /*
    Config services - Ces processors doivent être définis dans la configuration principale de l'application
    Les classes de base sont fournies par le plugin
    */
    'email-in' => [
        // \App\Services\Processors\Emails\EmailAnalyserProcessor::class,
        \App\Services\Processors\Emails\HelloWorldProcessor::class,
        // Processors définis dans config('msgraph-email.email-in') de l'application principale
    ],
    'email-draft' => [
        \App\Services\Processors\Emails\HelloWorldDraftProcessor::class,
        // \App\Services\Processors\Emails\DraftEmailProcessor::class,
        \App\Services\Processors\Emails\TradEmailProcessor::class,
    ],




    'subscription_refresh' => [
        'time' => env('MSGRAPH_SUBSCRIPTION_REFRESH_TIME', '18:00'),
        'timezone' => env('MSGRAPH_SUBSCRIPTION_REFRESH_TIMEZONE', 'Europe/Paris'),
    ],

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | Specify the User model class that should be used for linking MsgGraph
    | users with your application users. This model should have an 'email' 
    | field that can be matched with Microsoft Graph user emails.
    |
    */
    'user_model' => App\Models\User::class,

    /*
    * the clientId is set from the Microsoft portal to identify the application
    * https://apps.dev.microsoft.com
    */
    'clientId' => env('MSGRAPH_CLIENT_ID'),
    'tenantId' => env('MSGRAPH_TENANT_ID'),
    'clientSecret' => env('MSGRAPH_SECRET_ID'),
    'tenantUrlAuthorize' => env('MSGRAPH_TENANT_AUTHORIZE'),
    'tenantUrlAccessToken' => env('MSGRAPH_TENANT_TOKEN', 'https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/token'),
    'urlAuthorize' => 'https://login.microsoftonline.com/' . env('MSGRAPH_TENANT_ID', 'common') . '/oauth2/v2.0/authorize',
    'urlAccessToken' => 'https://login.microsoftonline.com/' . env('MSGRAPH_TENANT_ID', 'common') . '/oauth2/v2.0/token',
    'urlResourceOwnerDetails' => 'https://login.microsoftonline.com/' . env('MSGRAPH_TENANT_ID', 'common') . '/oauth2/v2.0/resource',
    'scopes' => 'offline_access openid calendars.readwrite contacts.readwrite files.readwrite mail.readwrite mail.send tasks.readwrite mailboxsettings.readwrite user.readwrite',
    'preferTimezone' => env('MSGRAPH_PREFER_TIMEZONE', 'outlook.timezone="Europe/London"'),
    'dbConnection' => env('MSGRAPH_DB_CONNECTION', 'mysql'),
    'webhook_base_url' => env('WEBHOOK_BASE_URL', null),




];
