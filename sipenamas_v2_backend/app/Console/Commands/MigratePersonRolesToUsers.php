<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sekali jalan (idempotent - aman diulang): pindahkan role & permission
 * Spatie yang SEKARANG masih menempel di `Person` (peninggalan sebelum
 * pemisahan auth/RBAC ke `User`) ke `User` yang berkorespondensi
 * (by kodeperson). TIDAK menghapus apa pun dari Person/model_has_roles
 * lama - dibiarkan sebagai arsip. Jalankan SyncUsersFromUwmsdm dulu
 * supaya sebagian besar User target sudah ada sebelum command ini jalan;
 * sisanya (akun eksternal, atau dosen yang belum ke-sync) dibuat di sini
 * langsung dari data Person yang ada.
 */
class MigratePersonRolesToUsers extends Command
{
    protected $signature = 'rbac:migrate-person-roles-to-users';

    protected $description = 'Pindahkan role/permission Spatie dari Person (legacy) ke User (baru), by kodeperson';

    public function handle(): int
    {
        $personIds = DB::table('model_has_roles')->where('model_type', Person::class)->pluck('model_id')
            ->merge(DB::table('model_has_permissions')->where('model_type', Person::class)->pluck('model_id'))
            ->unique();

        $persons = Person::whereIn('id', $personIds)->get();

        $this->info("Ditemukan {$persons->count()} Person dengan role/permission untuk dipindah.");

        $usersTouched = 0;

        DB::transaction(function () use ($persons, &$usersTouched) {
            foreach ($persons as $person) {
                $user = User::where('kodeperson', $person->KODEPERSON)->first();

                // Fallback by NIDN - kalau NIP orang ini sudah berubah di
                // uwmsdm sejak Person.KODEPERSON terakhir ditulis, User yang
                // benar sudah dibuat SyncUsersFromUwmsdm dengan NIP baru.
                // Tanpa fallback ini, kita akan bikin User DUPLIKAT dengan
                // NIP lama yang tidak akan pernah bisa login (username SSO
                // orang ini adalah NIP barunya) - role jadi menempel di
                // akun yang salah/mati (lihat temuan 2026-09-17: 9 kasus).
                if (! $user && $person->NIDN) {
                    $user = User::where('nidn', $person->NIDN)->first();
                }

                // Fallback kedua by email institusi - staff non-dosen
                // biasanya tidak punya NIDN sama sekali, jadi NIP berubah
                // tanpa email juga berarti tidak ada tempat untuk fallback
                // dan endingnya adalah akun duplikat (satu bisa login tanpa
                // role, satu punya role tapi NIP-nya sudah mati).
                if (! $user && $person->EMAIL) {
                    $user = User::where('email', $person->EMAIL)->first();
                }

                if ($user && $user->kodeperson !== $person->KODEPERSON) {
                    // Person.KODEPERSON (NIP lama) jadi alias supaya
                    // Penelitian/Abdimas/dst yang tersimpan di NIP lama
                    // tetap ketemu lewat User::kodepersonAliases().
                    DB::table('user_kodeperson_aliases')->updateOrInsert(
                        ['kodeperson' => $person->KODEPERSON],
                        ['user_id' => $user->id]
                    );
                }

                if (! $user) {
                    $user = User::create([
                        'kodeperson' => $person->KODEPERSON,
                        'nama' => $person->NAMALENGKAP,
                        'email' => $person->EMAIL,
                        'nidn' => $person->NIDN,
                        'is_external' => (bool) $person->ISEXTERNAL,
                        'paswet' => $person->PASWET,
                    ]);
                } elseif ($person->ISEXTERNAL) {
                    // User ini sudah lebih dulu dibuat SyncUsersFromUwmsdm
                    // (ketemu juga di ms_pegawai) yang selalu set is_external
                    // ke false - Person.ISEXTERNAL=1 lebih otoritatif untuk
                    // "akun ini login pakai password lokal, bukan SSO", jadi
                    // dikoreksi di sini. Tanpa ini akun ybs salah diarahkan
                    // ke jalur SSO saat login dan selalu gagal.
                    $user->forceFill([
                        'is_external' => true,
                        'paswet' => $person->PASWET,
                    ])->save();
                }

                // Person.ISEXTERNAL=false bilang "pakai SSO", tapi kalau
                // User ini tidak pernah dikonfirmasi aktif di uwmsdm
                // (synced_at null - SyncUsersFromUwmsdm belum/tidak pernah
                // menemukan orang ini di sc_user+ms_pegawai aktif), SSO
                // pasti selalu menolaknya. Tanpa ini akun ybs punya role
                // tapi tidak punya jalur login yang benar-benar jalan.
                if (! $user->is_external && ! $user->synced_at) {
                    $user->forceFill([
                        'is_external' => true,
                        'paswet' => $person->PASWET,
                    ])->save();
                }

                $roleNames = DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_type', Person::class)
                    ->where('model_has_roles.model_id', $person->id)
                    ->pluck('roles.name');

                $permissionNames = DB::table('model_has_permissions')
                    ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                    ->where('model_has_permissions.model_type', Person::class)
                    ->where('model_has_permissions.model_id', $person->id)
                    ->pluck('permissions.name');

                $user->syncRoles($roleNames->all());
                $user->syncPermissions($permissionNames->all());

                $usersTouched++;
            }
        });

        $verifyCount = User::whereHas('roles')->count();

        $this->info("Selesai. {$usersTouched} User disentuh. Verifikasi: {$verifyCount} User sekarang punya minimal satu role.");

        return self::SUCCESS;
    }
}
