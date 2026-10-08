<?php

namespace Tests\Feature\Admin;

use App\Models\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodePelaksanaanTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_date_falls_back_to_start_date_when_empty(): void
    {
        $p = Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'TGLPELAKSANAANBEGIN' => '2026-03-10']);

        $this->assertSame('2026-03-10', $p->tglPelaksanaanSelesai());
    }

    public function test_end_date_falls_back_when_legacy_zero_date(): void
    {
        $p = Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'TGLPELAKSANAANBEGIN' => '2026-03-10']);
        $p->setRawAttributes(array_merge($p->getAttributes(), ['TGLPELAKSANAANEND' => '0000-00-00']));

        $this->assertSame('2026-03-10', $p->tglPelaksanaanSelesai());
    }

    public function test_explicit_end_date_is_used(): void
    {
        $p = Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'TGLPELAKSANAANBEGIN' => '2026-03-10', 'TGLPELAKSANAANEND' => '2026-03-20']);

        $this->assertSame('2026-03-20', $p->tglPelaksanaanSelesai());
    }

    public function test_no_dates_returns_null(): void
    {
        $this->assertNull(Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026])->tglPelaksanaanSelesai());
    }
}
