<?php

namespace App\Support\Rbac;

/**
 * Sumber kebenaran permission granular. Nama permission Spatie (tabel
 * `permissions`, dicek lewat `$user->can(...)`) disusun per RESOURCE
 * lintas modul mengikuti konvensi Spatie ("view articles" - huruf kecil
 * dipisah spasi), bukan per modul Role.
 *
 * `RESOURCE_PERMISSIONS` adalah katalog UNIVERSAL - tidak terikat role
 * manapun. Sejak gerbang route data dilepas dari middleware `role:` (cuma
 * disisakan di dashboard tiap modul), permission adalah satu-satunya
 * penentu akses data - role APAPUN (6 modul bawaan atau role custom yang
 * dibuat admin lewat Manajemen Role) bisa diberi kombinasi permission
 * apa saja dari katalog ini.
 *
 * `LEGACY_ACTION_KEYS` cuma memetakan key aksi PENDEK lama (`read`,
 * `submit-revisi`, dst) ke resource+aksi barunya, KHUSUS untuk 6 role
 * modul bawaan - key pendek ini yang dipakai `User::modulePermissions()`/
 * `Sidebar.jsx`/`authStore.hasPermission()` di frontend untuk menu &
 * dashboard, jangan diubah tanpa mengubah frontend juga. Role custom
 * tidak melalui jalur ini sama sekali (tidak dapat dashboard/menu
 * sidebar - lihat catatan desain di plan).
 */
final class PermissionCatalog
{
    /**
     * Resource => [key aksi granular => nama permission lengkap].
     * Ini yang ditampilkan Manajemen Role/Menu Permission untuk SEMUA
     * role - checkbox per resource+aksi asli.
     *
     * @var array<string, array<string, string>>
     */
    public const RESOURCE_PERMISSIONS = [
        'penelitian' => [
            'view' => 'view penelitian',
            'create' => 'create penelitian',
            'submit_revisi' => 'submit revisi penelitian',
            'submit_monev' => 'submit monev penelitian',
            'submit_laporan_akhir' => 'submit laporan akhir penelitian',
            'decide_final_approval' => 'decide final approval penelitian',
            'approve' => 'approve penelitian',
            'reject' => 'reject penelitian',
        ],
        'subsidi apc' => ['view' => 'view subsidi apc', 'create' => 'create subsidi apc'],
        'insentif' => ['view' => 'view insentif', 'create' => 'create insentif'],
        'hki' => ['view' => 'view hki', 'create' => 'create hki'],
        'periode' => [
            'view' => 'view periode',
            'create' => 'create periode',
            'toggle_aktif' => 'toggle aktif periode',
        ],
        'plotting' => [
            'view' => 'view plotting',
            'assign_reviewer' => 'assign reviewer plotting',
            'finalize' => 'finalize reviewer plotting',
            'add_reviewer' => 'add reviewer plotting',
            'assign_revisi_verifikator' => 'assign revisi verifikator plotting',
        ],
        'final approval' => ['view' => 'view final approval'],
        'user' => ['view' => 'view user', 'manage' => 'manage user'],
        'role' => ['view' => 'view role', 'manage' => 'manage role'],
        'basis data' => ['view' => 'view basis data', 'manage' => 'manage basis data'],
        'sinta' => ['export' => 'export sinta'],
        'penugasan' => [
            'confirm_kesediaan' => 'confirm kesediaan penugasan',
            'submit_penilaian' => 'submit penilaian penugasan',
            'view_revisi_queue' => 'view revisi queue penugasan',
            'verifikasi_revisi' => 'verifikasi revisi penugasan',
        ],
        // RKT & AKR murni monitoring/pelaporan - tidak ada aksi tulis yang
        // punya alur/kolom bisnis sendiri (lihat catatan di
        // Api\V1\Rektorat\DashboardController & Api\V1\Akreditasi\ReportController).
        'rektorat' => ['view' => 'view rektorat'],
        'akreditasi' => ['view' => 'view akreditasi'],
    ];

    /**
     * Key aksi pendek LAMA (dari sebelum RBAC per-resource) => daftar
     * pasangan [resource, key aksi granular] di RESOURCE_PERMISSIONS yang
     * digenggamnya. KHUSUS 6 role modul bawaan - dipakai
     * User::modulePermissions()/Sidebar/UserController, BUKAN Manajemen
     * Role (itu pakai allResourceGroups() langsung, berlaku utk semua role).
     *
     * @var array<string, array<string, list<array{0: string, 1: string}>>>
     */
    private const LEGACY_ACTION_KEYS = [
        'PEN' => [
            'read' => [['penelitian', 'view'], ['subsidi apc', 'view'], ['insentif', 'view'], ['hki', 'view']],
            'create' => [['penelitian', 'create'], ['subsidi apc', 'create'], ['insentif', 'create'], ['hki', 'create']],
            'submit-revisi' => [['penelitian', 'submit_revisi']],
            'submit-monev' => [['penelitian', 'submit_monev']],
            'submit-laporan-akhir' => [['penelitian', 'submit_laporan_akhir']],
        ],
        'ADM' => [
            'read' => [['periode', 'view'], ['plotting', 'view'], ['final approval', 'view'], ['user', 'view'], ['role', 'view'], ['basis data', 'view']],
            'create' => [['periode', 'create']],
            'assign-reviewer' => [['plotting', 'assign_reviewer'], ['plotting', 'finalize'], ['plotting', 'add_reviewer'], ['plotting', 'assign_revisi_verifikator']],
            'decide-final-approval' => [['penelitian', 'decide_final_approval']],
            'toggle-periode-aktif' => [['periode', 'toggle_aktif']],
            'manage-users' => [['user', 'manage']],
            'manage-roles' => [['role', 'manage']],
            'export-sinta' => [['sinta', 'export']],
            'manage-basisdata' => [['basis data', 'manage']],
        ],
        'REV' => [
            'read' => [['penelitian', 'view']],
            'confirm-kesediaan' => [['penugasan', 'confirm_kesediaan']],
            'submit-penilaian' => [['penugasan', 'submit_penilaian']],
            'verifikasi-revisi' => [['penugasan', 'view_revisi_queue'], ['penugasan', 'verifikasi_revisi']],
        ],
        'DKN' => [
            'read' => [['penelitian', 'view']],
            'approve-proposal' => [['penelitian', 'approve']],
            'reject-proposal' => [['penelitian', 'reject']],
        ],
        'RKT' => ['read' => [['rektorat', 'view']]],
        'AKR' => ['read' => [['akreditasi', 'view']]],
    ];

    /**
     * Seluruh resource & aksi granular di katalog universal, dipakai
     * Manajemen Role/Menu Permission untuk role APAPUN (fixed atau custom).
     *
     * @return list<array{resource: string, actions: list<array{key: string, name: string}>}>
     */
    public static function allResourceGroups(): array
    {
        $groups = [];

        foreach (self::RESOURCE_PERMISSIONS as $resource => $actions) {
            $groups[] = [
                'resource' => $resource,
                'actions' => collect($actions)->map(fn (string $name, string $key) => ['key' => $key, 'name' => $name])->values()->all(),
            ];
        }

        return $groups;
    }

    /** @return list<string> key aksi pendek LEGACY milik satu role modul bawaan */
    public static function legacyActionKeysFor(string $role): array
    {
        return array_keys(self::LEGACY_ACTION_KEYS[$role] ?? []);
    }

    /**
     * Semua nama permission lengkap untuk satu key aksi pendek LEGACY
     * dalam satu role modul bawaan - dipakai
     * User::modulePermissions()/UserController.
     *
     * @return list<string>
     */
    public static function permissionNamesForLegacyAction(string $role, string $actionKey): array
    {
        $pairs = self::LEGACY_ACTION_KEYS[$role][$actionKey] ?? [];

        return array_values(array_unique(array_map(
            fn (array $pair) => self::RESOURCE_PERMISSIONS[$pair[0]][$pair[1]] ?? null,
            $pairs
        )));
    }

    /** @return list<string> seluruh nama permission unik di katalog universal */
    public static function allPermissionNames(): array
    {
        $names = [];

        foreach (self::RESOURCE_PERMISSIONS as $actions) {
            array_push($names, ...array_values($actions));
        }

        return array_values(array_unique($names));
    }

    /**
     * Label default saat permission pertama kali di-seed (lihat
     * PermissionSeeder). Admin bisa menggantinya lewat halaman Manajemen
     * Menu Permission - ini cuma nilai awal, bukan sumber kebenaran
     * permanen (itu ada di kolom `permissions.label`).
     *
     * @var array<string, string> nama permission lengkap => label
     */
    public const DEFAULT_LABELS = [
        'view penelitian' => 'Lihat Penelitian',
        'create penelitian' => 'Tambah Penelitian',
        'submit revisi penelitian' => 'Ajukan Revisi',
        'submit monev penelitian' => 'Isi Monev Hasil',
        'submit laporan akhir penelitian' => 'Ajukan Laporan Akhir',
        'decide final approval penelitian' => 'Putuskan Persetujuan Akhir',
        'approve penelitian' => 'Setujui Usulan',
        'reject penelitian' => 'Tolak Usulan',
        'view subsidi apc' => 'Lihat Subsidi APC',
        'create subsidi apc' => 'Tambah Subsidi APC',
        'view insentif' => 'Lihat Insentif',
        'create insentif' => 'Tambah Insentif',
        'view hki' => 'Lihat HKI',
        'create hki' => 'Tambah HKI',
        'view periode' => 'Lihat Periode',
        'create periode' => 'Tambah / Ubah Periode',
        'toggle aktif periode' => 'Aktif/Nonaktifkan Periode',
        'view plotting' => 'Lihat Plotting',
        'assign reviewer plotting' => 'Tetapkan Reviewer',
        'finalize reviewer plotting' => 'Finalisasi Reviewer',
        'add reviewer plotting' => 'Tambah Reviewer Ke-3',
        'assign revisi verifikator plotting' => 'Tunjuk Verifikator Revisi',
        'view final approval' => 'Lihat Final Approval',
        'view user' => 'Lihat Pengguna',
        'manage user' => 'Kelola Pengguna',
        'view role' => 'Lihat Peran',
        'manage role' => 'Kelola Peran',
        'view basis data' => 'Lihat Basis Data',
        'manage basis data' => 'Kelola Basis Data',
        'export sinta' => 'Ekspor SINTA',
        'confirm kesediaan penugasan' => 'Konfirmasi Kesediaan',
        'submit penilaian penugasan' => 'Isi Penilaian',
        'view revisi queue penugasan' => 'Lihat Antrian Verifikasi Revisi',
        'verifikasi revisi penugasan' => 'Verifikasi Revisi',
        'view rektorat' => 'Lihat Dashboard Rektorat',
        'view akreditasi' => 'Lihat Dashboard Akreditasi',
    ];

    public static function defaultLabelFor(string $permissionName): string
    {
        return self::DEFAULT_LABELS[$permissionName] ?? str($permissionName)->title()->toString();
    }
}
