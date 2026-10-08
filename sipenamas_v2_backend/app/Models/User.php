<?php

namespace App\Models;

use App\Enums\Role;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Identitas login + RBAC Sipenamas v2 - LEPAS TOTAL dari `Person` (tabel
 * legacy `person`, tetap dipertahankan sebagai anchor 9 relasi bisnis
 * historis - lihat catatan di Person.php). Data identitas (`kodeperson`,
 * `nidn`, `nama`, `email`, `kodeprodi`) di-refresh dari database pusat
 * UKWMS (`uwmsdm.sc_user`+`ms_pegawai`, read-only) lewat
 * `App\Console\Commands\SyncUsersFromUwmsdm`, bukan realtime saat login.
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;

    /**
     * Role Spatie bypass lintas-modul (dicek lewat Gate::before di
     * AppServiceProvider), menggantikan kolom legacy ISSUPERUSER sebagai
     * sumber keputusan otorisasi.
     */
    public const SUPER_ADMIN_ROLE = 'Super Admin';

    protected $table = 'users';

    /**
     * Role dan permission Spatie semuanya ber-guard `sanctum`. Dipatok di
     * sini supaya pengecekan tetap ke guard itu saat request memakai guard
     * session `web` (halaman Inertia), bukan mencari permission guard `web`.
     */
    protected string $guard_name = 'sanctum';

    protected $guarded = [];

    protected $hidden = [
        'paswet',
    ];

    protected function casts(): array
    {
        return [
            'is_external' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function getAuthIdentifierName()
    {
        return 'kodeperson';
    }

    /**
     * Dipakai untuk route model binding ({user} bila ada) - konsisten
     * dengan PersonResource yang memakai kodeperson sebagai `id` publik,
     * bukan primary key auto-increment.
     */
    public function getRouteKeyName(): string
    {
        return 'kodeperson';
    }

    /**
     * Link opsional ke data histori/riset (tabel legacy `person`) untuk
     * kebutuhan tampilan (prodi/fakultas dari sisi `person` bila
     * `kodeprodi` lokal belum terisi). TIDAK dipakai sebagai FK relasi
     * bisnis - 9 relasi Penelitian/Abdimas/dst tetap menunjuk ke
     * `person.KODEPERSON` langsung, bukan lewat User.
     */
    public function person()
    {
        return $this->belongsTo(Person::class, 'kodeperson', 'KODEPERSON');
    }

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'kodeprodi', 'KODEPRODI');
    }

    /** @var array<int, string>|null */
    private ?array $storedKodepersonAliases = null;

    /**
     * Semua KODEPERSON yang pernah dipakai orang ini (NIP terbukti bisa
     * berubah seumur karier - lihat catatan riset di Person.php/
     * SyncUsersFromUwmsdm). Dipakai oleh scope kepemilikan data bisnis
     * (Penelitian::forPeneliti() dkk) supaya riwayat penelitian/abdimas
     * lama (tersimpan di NIP lama) tetap kelihatan walau login pakai NIP
     * baru. Selalu minimal berisi kodeperson miliknya sendiri. Di-cache per
     * instance karena PenelitianResource memanggilnya untuk setiap baris.
     *
     * @return array<int, string>
     */
    public function kodepersonAliases(): array
    {
        $this->storedKodepersonAliases ??= DB::table('user_kodeperson_aliases')
            ->where('user_id', $this->id)
            ->pluck('kodeperson')
            ->all();

        return array_values(array_unique(array_filter([$this->kodeperson, ...$this->storedKodepersonAliases])));
    }

    /**
     * Padanan Person::allowedRoles() - lihat catatan di sana. Sumber
     * kebenaran RBAC tetap tabel Spatie (roles/model_has_roles), sekarang
     * di-attach ke User, bukan Person.
     *
     * @return array<int, Role>
     */
    public function allowedRoles(): array
    {
        if ($this->hasRole(self::SUPER_ADMIN_ROLE)) {
            return Role::cases();
        }

        return array_values(array_filter(
            Role::cases(),
            fn (Role $role) => $this->hasRole($role->value)
        ));
    }

    public function defaultRole(): ?Role
    {
        return $this->allowedRoles()[0] ?? null;
    }

    /**
     * Permission CRUD granular per modul yang dimiliki user ini saat ini,
     * mis. ['PEN' => ['create','read'], 'ADM' => ['read']]. Padanan
     * Person::modulePermissions().
     *
     * @param  array<int, Role>|null  $allowedRoles  hasil allowedRoles() bila pemanggil sudah menghitungnya
     * @return array<string, array<int, string>>
     */
    public function modulePermissions(?array $allowedRoles = null): array
    {
        // Satu set nama permission per user, bukan Gate can() per nama -
        // list Manajemen User memanggil ini untuk ratusan user sekaligus.
        $isSuperAdmin = $this->hasRole(self::SUPER_ADMIN_ROLE);
        $granted = $isSuperAdmin ? [] : $this->getAllPermissions()->pluck('name')->flip()->all();

        return collect($allowedRoles ?? $this->allowedRoles())
            ->mapWithKeys(function (Role $role) use ($isSuperAdmin, $granted) {
                // Satu key aksi bisa menggenggam >1 permission lintas
                // resource (mis. PEN.view = penelitian+subsidi apc+
                // insentif+hki) - dianggap "granted" cuma kalau SEMUA
                // permission di baliknya dipegang user ini.
                $actions = array_values(array_filter(
                    PermissionCatalog::legacyActionKeysFor($role->value),
                    function (string $actionKey) use ($role, $isSuperAdmin, $granted) {
                        $names = PermissionCatalog::permissionNamesForLegacyAction($role->value, $actionKey);

                        return ! empty($names) && ($isSuperAdmin || collect($names)->every(fn (string $n) => isset($granted[$n])));
                    }
                ));

                return [$role->value => $actions];
            })
            ->all();
    }

    /**
     * Tulis-ulang kolom legacy GROUPAKSES_* pada baris `person` yang
     * terhubung (by kodeperson) dari state role Spatie User saat ini,
     * supaya app PHP lama (yang membaca kolom tsb langsung dari `person`)
     * tetap melihat hak akses yang akurat walau RBAC sumber kebenarannya
     * sekarang ada di User, bukan Person. Sinkronisasi ini SATU ARAH
     * (Spatie User -> kolom legacy person) - no-op bila user ini tidak
     * punya baris `person` yang terhubung (mis. akun eksternal murni).
     */
    public function syncGroupAksesColumns(): void
    {
        $person = $this->person;

        if (! $person) {
            return;
        }

        foreach (Role::cases() as $role) {
            $column = $role->groupAksesColumn();

            if (! $this->hasRole($role->value)) {
                $person->{$column} = null;

                continue;
            }

            // JANGAN timpa kode grup granular yang sudah ada (mis. 'ADP01').
            if (blank($person->{$column})) {
                $person->{$column} = $role->value.'01';
            }
        }

        $person->save();
    }
}
