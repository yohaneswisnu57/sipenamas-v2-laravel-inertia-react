<?php

namespace App\Services\Auth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Port dari dologinpegawai_curl() di appz/posko/myfunctions.php - memanggil
 * API SSO pegawai UKWMS untuk otentikasi dosen/staf internal
 * (person.ISEXTERNAL = 0). JANGAN diganti jadi pencocokan password lokal;
 * itu akan memutus integrasi akun SSO universitas (lihat .agents/context.md).
 */
class UkwmsSsoClient
{
    /**
     * @return array{success: bool, namaLengkap: ?string, raw: array}
     */
    public function login(string $userid, string $password): array
    {
        try {
            $response = Http::timeout(10)->withHeaders([
                'X-API-KEY' => config('services.ukwms_sso.api_key'),
                'userid' => $userid,
                'password' => $password,
            ])->post(config('services.ukwms_sso.url'), [
                'userid' => $userid,
                'password' => $password,
            ]);
        } catch (ConnectionException $e) {
            // Server SSO kampus tidak terjangkau (jaringan/SSL/timeout) -
            // jangan biarkan ini jadi 500 tak tertangani, perlakukan sebagai
            // login gagal supaya AuthController tetap merespons rapi.
            Log::warning('UKWMS SSO tidak terjangkau: '.$e->getMessage());

            return ['success' => false, 'namaLengkap' => null, 'raw' => []];
        }

        $body = $response->json() ?? [];
        $namaLengkap = data_get($body, 'data.auth.nama');

        return [
            'success' => data_get($body, 'status') === 200 && filled($namaLengkap),
            'namaLengkap' => $namaLengkap,
            'raw' => $body,
        ];
    }
}
