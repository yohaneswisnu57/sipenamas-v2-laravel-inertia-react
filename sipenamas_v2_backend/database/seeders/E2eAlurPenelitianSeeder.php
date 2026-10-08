<?php

namespace Database\Seeders;

use App\Models\Fakultas;
use App\Models\Mahasiswa;
use App\Models\Periode;
use App\Models\Person;
use App\Models\Prodi;
use App\Models\SkimPenelitian;
use App\Models\Soalkuesionerpeneliti;
use App\Models\SoalkuesionerpenelitiDetail;
use App\Models\SoalMonevPenelitian;
use App\Models\SoalPenilaianProposal;
use App\Models\TabelRencanaTarget;
use App\Models\User;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Data minimum untuk uji integrasi frontend <-> backend alur penelitian
 * (sipenamas_v2_frontend/tests/integration/alurPenelitian.integration.test.js):
 * master data satu fakultas, satu user per peran, dan token Sanctum-nya
 * ditulis ke berkas `E2E_TOKEN_FILE`. Hanya untuk database sqlite sekali
 * pakai dengan APP_ENV=testing - menolak jalan di environment lain.
 */
class E2eAlurPenelitianSeeder extends Seeder
{
    private const PENELITI = ['view penelitian', 'create penelitian', 'submit revisi penelitian', 'submit laporan akhir penelitian', 'submit monev penelitian'];

    private const REVIEWER = ['view penelitian', 'confirm kesediaan penugasan', 'submit penilaian penugasan', 'view revisi queue penugasan', 'verifikasi revisi penugasan'];

    private const DEKAN = ['view penelitian', 'approve penelitian', 'reject penelitian'];

    private const ADMIN = [
        'view plotting', 'assign reviewer plotting', 'finalize reviewer plotting', 'assign revisi verifikator plotting',
        'view final approval', 'decide final approval penelitian',
    ];

    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite') {
            throw new RuntimeException('E2eAlurPenelitianSeeder hanya untuk database sqlite dengan APP_ENV=testing.');
        }

        foreach (['PEN', 'DKN', 'ADM', 'REV'] as $role) {
            SpatieRole::findOrCreate($role, 'sanctum');
        }
        foreach (PermissionCatalog::allPermissionNames() as $izin) {
            SpatiePermission::findOrCreate($izin, 'sanctum');
        }

        Fakultas::create(['KODEFAKULTAS' => 'FT', 'NAMAFAKULTAS' => 'Teknik', 'KDDEKAN' => 'DKN01']);
        Prodi::create(['KODEPRODI' => 'TI', 'NAMAPRODI' => 'Teknik Informatika', 'KDFAKULTAS' => 'FT']);
        Periode::create(['KODEPERIODE' => '2026', 'TAHUN' => 2026, 'ISAKTIF' => 1, 'TGLBEGIN' => '2026-01-01', 'TGLEND' => '2026-12-01']);

        SkimPenelitian::create([
            'KODESKIM' => 'INT01', 'NAMASKIM' => 'Penelitian Dasar', 'ISAKTIF' => 1, 'ISABDIMAS' => 0,
            'STATUSPEN' => 'INTERNAL', 'KDSOALPENILAIANPROPOSAL' => 'S01',
        ]);
        $borang = SoalPenilaianProposal::create(['KODESOAL' => 'S01', 'DESKRIPSI' => 'Borang reguler']);
        $borang->detail()->create(['NOMOR' => 1, 'KRITERIAPENILAIAN' => 'Perumusan masalah', 'BOBOTPERSEN' => 60]);
        $borang->detail()->create(['NOMOR' => 2, 'KRITERIAPENILAIAN' => 'Metode', 'BOBOTPERSEN' => 40]);

        TabelRencanaTarget::create(['KDSKIM' => 'INT01', 'KATEGORI' => 'Unggah laporan penelitian', 'ISWAJIB' => 1, 'INDIKATORNYA' => 'Selesai', 'URUTAN' => 70]);
        Mahasiswa::create(['NIM' => '5303021001', 'NAMAMAHASISWA' => 'Budi']);

        $kuesioner = Soalkuesionerpeneliti::create(['KODESOAL' => 'KP01', 'ISAKTIF' => 1]);
        SoalkuesionerpenelitiDetail::create(['IDPARENT' => $kuesioner->id, 'NOMOR' => 1, 'KELOMPOK' => 'A', 'URAIAN' => 'Petugas ramah']);

        foreach (range(1, 8) as $nomor) {
            SoalMonevPenelitian::create(['NOMOR' => $nomor, 'ASPEKPENILAIAN' => "Aspek {$nomor}", 'PIL01' => 'Tidak', 'PIL02' => 'Ya']);
        }

        $tokens = [
            'ketua' => $this->pengguna('PEN01', 'Dr. Ketua', self::PENELITI, [], ['PEN']),
            'anggota' => $this->pengguna('PEN02', 'Dr. Anggota', self::PENELITI, [], ['PEN']),
            'gjm' => $this->pengguna('GJM01', 'Dr. GJM', self::PENELITI, ['ISGJM' => 1], ['PEN']),
            'dekan' => $this->pengguna('DKN01', 'Prof. Dekan', self::DEKAN, [], ['PEN', 'DKN']),
            'admin' => $this->pengguna('ADM01', 'Admin LPPM', self::ADMIN, [], ['ADM']),
            'reviewer1' => $this->pengguna('REV01', 'Reviewer Satu', self::REVIEWER, ['ISREVIEWERPENELITIAN' => 1], ['PEN', 'REV']),
            'reviewer2' => $this->pengguna('REV02', 'Reviewer Dua', self::REVIEWER, ['ISREVIEWERPENELITIAN' => 1], ['PEN', 'REV']),
        ];

        if ($path = env('E2E_TOKEN_FILE')) {
            file_put_contents($path, json_encode($tokens, JSON_PRETTY_PRINT));
        }
    }

    /**
     * @param  list<string>  $izin
     * @param  array<string, mixed>  $person
     */
    private function pengguna(string $kodeperson, string $nama, array $izin, array $person = [], array $roles = []): string
    {
        $user = User::create(['kodeperson' => $kodeperson, 'nama' => $nama, 'kodeprodi' => 'TI', 'is_external' => false]);
        $user->givePermissionTo($izin);
        if ($roles) {
            $user->assignRole($roles);
        }
        Person::create(['KODEPERSON' => $kodeperson, 'NAMALENGKAP' => $nama, 'KDPRODI' => 'TI'] + $person);

        return $user->createToken('e2e')->plainTextToken;
    }
}
