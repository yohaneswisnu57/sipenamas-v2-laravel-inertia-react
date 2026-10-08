<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pengaman: RefreshDatabase menjalankan migrate:fresh, jadi test tidak
     * boleh pernah jalan di koneksi selain sqlite (mis. MySQL lokal/produksi).
     * Dicek di sini karena createApplication() jalan sebelum trait test di-setup.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $koneksi = $app['config']->get('database.default');

        if ($koneksi !== 'sqlite') {
            throw new RuntimeException("Test dihentikan: koneksi database '{$koneksi}', bukan sqlite. Periksa phpunit.xml / cache config.");
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Aksi web (halaman Inertia) menjawab redirect + flash, bukan JSON.
         * Berhasil: redirect tanpa error validasi maupun flash `error`.
         */
        TestResponse::macro('assertAksiBerhasil', function (?string $pesan = null) {
            /** @var TestResponse $this */
            $this->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error');

            if ($pesan !== null) {
                $this->assertSessionHas('success', $pesan);
            }

            return $this;
        });

        // Ambil prop halaman Inertia dengan dot notation, termasuk wildcard
        // (`items.*.id`), padanan ->json() untuk response JSON.
        TestResponse::macro('inertiaProp', function (string $path) {
            /** @var TestResponse $this */
            return data_get($this->inertiaProps(), $path);
        });

        // Padanan assertJsonPath / assertJsonCount untuk props halaman Inertia.
        TestResponse::macro('assertProp', function (string $path, mixed $expected) {
            /** @var TestResponse $this */
            $actual = $this->inertiaProp($path);

            $expected instanceof \Closure
                ? Assert::assertTrue($expected($actual), "Prop [{$path}] tidak lolos pemeriksaan.")
                : Assert::assertSame($expected, $actual, "Prop [{$path}] tidak sesuai.");

            return $this;
        });

        TestResponse::macro('assertPropCount', function (int $count, string $path) {
            /** @var TestResponse $this */
            Assert::assertCount($count, $this->inertiaProp($path), "Jumlah prop [{$path}] tidak sesuai.");

            return $this;
        });

        // Ditolak aturan bisnis (abort 4xx): redirect kembali dengan flash `error`.
        TestResponse::macro('assertAksiDitolak', function (?string $pesan = null) {
            /** @var TestResponse $this */
            $this->assertRedirect();

            $pesan === null ? $this->assertSessionHas('error') : $this->assertSessionHas('error', $pesan);

            return $this;
        });
    }
}
