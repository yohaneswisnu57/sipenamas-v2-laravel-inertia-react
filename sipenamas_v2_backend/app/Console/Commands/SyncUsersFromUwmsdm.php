<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Refresh identitas login dari database pusat UKWMS (uwmsdm.sc_user +
 * ms_pegawai, read-only) ke tabel lokal `users`. TIDAK PERNAH menulis
 * apa pun ke koneksi `uwmsdm` - hanya SELECT. Upsert-only (tidak pernah
 * menghapus User yang sudah ada), sama seperti pola sync legacy yang
 * sudah terbukti aman untuk `person`.
 *
 * Kunci pencocokan: `sc_user.userid = ms_pegawai.nip` dulu; kalau NIP
 * seseorang berubah (pindah unit - lihat temuan riset di plan), upsert
 * lokal jatuh ke NIDN sebagai kunci sekunder supaya role/token yang
 * sudah dipunya user tsb tidak hilang gara-gara "akun baru" ke-buat.
 */
class SyncUsersFromUwmsdm extends Command
{
    protected $signature = 'rbac:sync-users-from-uwmsdm';

    protected $description = 'Refresh tabel users lokal dari uwmsdm.sc_user + ms_pegawai (read-only, upsert-only)';

    public function handle(): int
    {
        $rows = DB::connection('uwmsdm')->select(
            "SELECT
                su.userid,
                su.statususer,
                mp.nip,
                mp.nidn,
                mp.nama,
                mp.email_inst,
                mp.email,
                mp.idhomebase,
                mp.idstatusaktif
             FROM sc_user su
             LEFT JOIN ms_pegawai mp ON su.userid = mp.nip
             WHERE su.statususer = '1'"
        );

        $homebaseMap = DB::table('homebase_prodi_map')->pluck('kodeprodi', 'idhomebase');

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (! $row->nip) {
                // userid tidak match ms_pegawai.nip sama sekali (akun
                // sistem/non-pegawai di sc_user) - lewati, bukan cakupan kita.
                $skipped++;

                continue;
            }

            $kodeperson = trim($row->userid);
            $nidn = $row->nidn ? trim($row->nidn) : null;
            $email = trim((string) ($row->email_inst ?: $row->email)) ?: null;

            // Cari dulu by kodeperson (kasus normal), fallback by NIDN kalau
            // NIP orang ini berubah sejak sync terakhir (lihat catatan kelas).
            $user = User::where('kodeperson', $kodeperson)->first();

            if (! $user && $nidn) {
                $user = User::where('nidn', $nidn)->first();
            }

            // Fallback kedua by email institusi - staff non-dosen biasanya
            // tidak punya NIDN, jadi ini satu-satunya jalan supaya NIP yang
            // berubah tidak bikin akun duplikat (role/token nyangkut di
            // akun lama yang NIP-nya sudah mati).
            if (! $user && $email) {
                $user = User::where('email', $email)->first();
            }

            $attributes = [
                'kodeperson' => $kodeperson,
                'nidn' => $nidn,
                'nama' => $row->nama ?: $kodeperson,
                'email' => $email,
                'kodeprodi' => $homebaseMap[$row->idhomebase] ?? null,
                'status_aktif' => $row->idstatusaktif,
                'synced_at' => now(),
            ];

            // is_external sengaja TIDAK ditimpa untuk user yang sudah ada -
            // seseorang bisa muncul di ms_pegawai (bekas pegawai) tapi tetap
            // butuh login pakai password lokal (lihat
            // MigratePersonRolesToUsers untuk sumber otoritatifnya, Person.
            // ISEXTERNAL). Cuma user BARU yang di-default false di sini.
            if (! $user) {
                $attributes['is_external'] = false;
            }

            if ($user) {
                $oldKodeperson = $user->kodeperson;
                $user->fill($attributes)->save();

                // NIP orang ini berganti sejak terakhir sync (ketemu via
                // fallback NIDN) - simpan NIP lama sebagai alias supaya
                // riwayat penelitian/abdimas di bawah NIP lama tetap
                // ketemu lewat User::kodepersonAliases().
                if ($oldKodeperson && $oldKodeperson !== $kodeperson) {
                    DB::table('user_kodeperson_aliases')->updateOrInsert(
                        ['kodeperson' => $oldKodeperson],
                        ['user_id' => $user->id]
                    );
                }

                $updated++;
            } else {
                $user = User::create($attributes);
                $created++;
            }

            // Kumpulkan SEMUA nip yang pernah terdaftar untuk nidn yang sama
            // (bukan cuma transisi lama->baru barusan) - dosen bisa punya
            // lebih dari 2 NIP seumur karier (pindah unit berkali-kali).
            if ($nidn) {
                $allNips = DB::connection('uwmsdm')
                    ->select('SELECT DISTINCT nip FROM ms_pegawai WHERE nidn = ? AND nip IS NOT NULL', [$nidn]);

                foreach ($allNips as $r) {
                    $alias = trim($r->nip);

                    if ($alias === $kodeperson) {
                        continue;
                    }

                    DB::table('user_kodeperson_aliases')->updateOrInsert(
                        ['kodeperson' => $alias],
                        ['user_id' => $user->id]
                    );
                }
            }
        }

        $this->info("Selesai. {$created} user dibuat, {$updated} user diperbarui, {$skipped} akun sc_user dilewati (tidak match ms_pegawai).");

        return self::SUCCESS;
    }
}
