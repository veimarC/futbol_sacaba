<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Credenciales de WhatsApp Cloud API. Los valores sensibles viven
    // exclusivamente en .env y nunca deben escribirse en controladores.
    'whatsapp' => [
        'enabled' => filter_var(env('WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'version' => env('WHATSAPP_GRAPH_VERSION', 'v25.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'templates' => [
            'team_request' => env('WHATSAPP_TEMPLATE_TEAM_REQUEST', 'solicitud_equipo_recibida'),
            'player_match' => env('WHATSAPP_TEMPLATE_PLAYER_MATCH', 'partido_jugador_programado'),
            'delegate_match' => env('WHATSAPP_TEMPLATE_DELEGATE_MATCH', 'partido_delegado_programado'),
            'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'es'),
        ],
    ],

];
