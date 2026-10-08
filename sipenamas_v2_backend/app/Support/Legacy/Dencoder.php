<?php

namespace App\Support\Legacy;

/**
 * Port 1:1 dari appz/posko/dencoder.php pada aplikasi legacy.
 * Dipakai HANYA untuk mencocokkan `person.PASWET` milik reviewer/user
 * eksternal (ISEXTERNAL = 1) agar akun lama tetap bisa login di v2.
 * Bukan mekanisme hashing yang aman - jangan dipakai untuk data baru.
 */
class Dencoder
{
    public static function encode3t(string $str): string
    {
        for ($i = 0; $i < 3; $i++) {
            $str = strrev(base64_encode($str));
        }

        return $str;
    }

    public static function decode3t(string $str): string
    {
        for ($i = 0; $i < 3; $i++) {
            $str = base64_decode(strrev($str));
        }

        return $str;
    }
}
