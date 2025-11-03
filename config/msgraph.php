<?php

return [

    /*
    * the clientId is set from the Microsoft portal to identify the application
    * https://apps.dev.microsoft.com
    */
    'clientId' => env('MSGRAPH_CLIENT_ID'),

    /*
    * set the tenant id
    */
    'tenantId' => env('MSGRAPH_TENANT_ID'),

    /*
    * set the application secret id
    */

    'clientSecret' => env('MSGRAPH_SECRET_ID'),

    /*
    * Note: OAuth redirects not needed for client_credentials flow (Application permissions)
    */

    /*
    set the tenant authorize url
    */

    'tenantUrlAuthorize' => env('MSGRAPH_TENANT_AUTHORIZE'),

    /*
    set the tenant token url
    */
    'tenantUrlAccessToken' => env('MSGRAPH_TENANT_TOKEN', 'https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/token'),

    /*
    set the authorize url
    */
    'urlAuthorize' => 'https://login.microsoftonline.com/' . env('MSGRAPH_TENANT_ID', 'common') . '/oauth2/v2.0/authorize',

    /*
    set the token url
    */
    'urlAccessToken' => 'https://login.microsoftonline.com/' . env('MSGRAPH_TENANT_ID', 'common') . '/oauth2/v2.0/token',

    /*
    set the resource url
    */
    'urlResourceOwnerDetails' => 'https://login.microsoftonline.com/' . env('MSGRAPH_TENANT_ID', 'common') . '/oauth2/v2.0/resource',

    /*
    set the scopes to be used, Microsoft Graph API will accept up to 20 scopes
    */

    'scopes' => 'offline_access openid calendars.readwrite contacts.readwrite files.readwrite mail.readwrite mail.send tasks.readwrite mailboxsettings.readwrite user.readwrite',

    /*
    The default timezone is set to Europe/London this option allows you to set your prefered timetime
    */
    'preferTimezone' => env('MSGRAPH_PREFER_TIMEZONE', 'outlook.timezone="Europe/London"'),

    /*
    set the database connection
    */
    'dbConnection' => env('MSGRAPH_DB_CONNECTION', 'mysql'),

    /*
    Config services
    */
    'email-in' => [
        \App\Services\Processors\Emails\EmailInClientProcessor::class,
        \App\Services\Processors\Emails\ContactLookupProcessor::class,
    ],
    'email-draft' => [
        \App\Services\Processors\Emails\DraftEmailProcessor::class,
        \App\Services\Processors\Emails\TradEmailProcessor::class,
    ],

    /*
    URL de base pour les webhooks (utiliser ngrok en local)
    */
    'webhook_base_url' => env('WEBHOOK_BASE_URL', null),

];
