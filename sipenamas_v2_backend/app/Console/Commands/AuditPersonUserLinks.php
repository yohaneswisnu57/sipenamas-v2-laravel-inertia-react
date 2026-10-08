<?php

namespace App\Console\Commands;

use App\Models\Fakultas;
use App\Models\HkiPeserta;
use App\Models\Insentif;
use App\Models\KegiatanAbdimas;
use App\Models\KegiatanAbdimasTim;
use App\Models\Penelitian;
use App\Models\PenelitianReviewer;
use App\Models\PenelitianTim;
use App\Models\SubsidiApc;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only: cek apakah setiap kode KODEPERSON yang dipakai di 9 relasi
 * bisnis historis (lihat catatan di Person.php) masih bisa diresolusikan
 * ke sebuah User (langsung via `users.kodeperson`, atau lewat riwayat NIP
 * di `user_kodeperson_aliases`). Kode yang tidak resolvable berarti
 * pemiliknya tidak punya akun untuk login sama sekali walau punya data
 * historis - dipakai untuk memandu SyncUsersFromUwmsdm/
 * MigratePersonRolesToUsers, bukan memperbaiki apa pun sendiri.
 */
class AuditPersonUserLinks extends Command
{
    protected $signature = 'rbac:audit-person-user-links';

    protected $description = 'Cek 9 relasi bisnis historis (Penelitian dkk) untuk kode KODEPERSON yang tidak resolvable ke User manapun';

    /**
     * @var array<int, array{0: class-string, 1: string}>
     */
    private const RELATIONS = [
        [HkiPeserta::class, 'NIP'],
        [Fakultas::class, 'KDDEKAN'],
        [KegiatanAbdimas::class, 'KDPERSONPENGAJU'],
        [PenelitianTim::class, 'NIKNIDN'],
        [KegiatanAbdimasTim::class, 'NIKNIDN'],
        [Insentif::class, 'KDPERSONPENGAJU'],
        [PenelitianReviewer::class, 'NIK'],
        [Penelitian::class, 'PERMOHONANDIBUAT_KDPERSON'],
        [SubsidiApc::class, 'KDPERSONPENGAJU'],
    ];

    public function handle(): int
    {
        $resolvable = User::query()->pluck('kodeperson')
            ->merge(DB::table('user_kodeperson_aliases')->pluck('kodeperson'))
            ->filter()
            ->map(fn (string $code) => trim($code))
            ->unique()
            ->flip();

        $totalChecked = 0;
        $totalUnresolved = 0;

        foreach (self::RELATIONS as [$modelClass, $column]) {
            $table = (new $modelClass)->getTable();

            $codes = DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->pluck($column)
                ->map(fn (string $code) => trim($code))
                ->unique();

            $unresolved = $codes->reject(fn (string $code) => $resolvable->has($code))->values();

            $totalChecked += $codes->count();
            $totalUnresolved += $unresolved->count();

            if ($unresolved->isNotEmpty()) {
                $this->warn("{$table}.{$column}: {$unresolved->count()} kode tidak resolvable ke User manapun:");

                foreach ($unresolved as $code) {
                    $this->line("  - {$code}");
                }
            }
        }

        $this->info("Selesai. {$totalChecked} kode unik diperiksa lintas 9 relasi historis, {$totalUnresolved} tidak resolvable ke User manapun.");

        return self::SUCCESS;
    }
}
