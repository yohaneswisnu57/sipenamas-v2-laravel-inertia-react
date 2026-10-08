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

    'libreoffice' => [
        'bin' => env('LIBREOFFICE_BIN', 'soffice'),
    ],

    // Alamat halaman verifikasi QR surat (padanan appz/dox legacy); QR berisi {url}/{JENIS}/{KODE}.
    'dox' => [
        'url' => env('DOX_URL') ?: 'https://sipenamasdev.ukwms.ac.id/dox',
    ],

    'ghostscript' => [
        'bin' => env('GHOSTSCRIPT_BIN', 'gs'),
    ],

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

    // Padanan $glb_pathlogin_pegawai di appz/posko/myfunctions.php
    'ukwms_sso' => [
        'url' => env('UKWMS_SSO_URL'),
        'api_key' => env('UKWMS_SSO_API_KEY'),
    ],

];
