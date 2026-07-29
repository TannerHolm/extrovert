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

    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
    ],

    'rapidapi' => [
        'key' => env('RAPIDAPI_KEY'),
        'instagram_host' => env('RAPIDAPI_INSTAGRAM_HOST', 'instagram-scraper-api2.p.rapidapi.com'),
        'tiktok_host' => env('RAPIDAPI_TIKTOK_HOST', 'tiktok-scraper10.p.rapidapi.com'),
    ],

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
    ],

    'anthropic' => [
        // Enables AI-polished agreement drafts; without it, drafting falls back
        // to a deterministic template merge.
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    'inbound_mail' => [
        // Domain configured for inbound parsing at the mail provider, e.g. in.extrovert.app.
        // When set, outreach Reply-To becomes reply+<token>@<domain> so replies land in the app.
        'domain' => env('INBOUND_MAIL_DOMAIN'),
        // Shared secret the provider includes when posting to the inbound webhook.
        'webhook_token' => env('INBOUND_MAIL_WEBHOOK_TOKEN'),
    ],

];
