<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Backend ini API-only (Bearer token via Sanctum), tidak memakai sesi
    | web/cookie seperti default Laravel. Guard default diarahkan ke
    | 'sanctum' dan provider ke App\Models\User - identitas login lokal
    | yang disuplai read-only dari database pusat UKWMS (uwmsdm.sc_user +
    | ms_pegawai). Model App\Models\Person (tabel legacy `person`) BUKAN
    | lagi dipakai untuk auth, cuma anchor relasi bisnis historis.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'sanctum'),
    ],

    'guards' => [
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],

        // Session cookie untuk halaman Inertia. Didaftarkan SETELAH sanctum
        // supaya guard default Spatie Permission tetap 'sanctum'.
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
    ],

];
