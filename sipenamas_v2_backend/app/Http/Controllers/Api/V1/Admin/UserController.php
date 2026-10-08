<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Http\Resources\PersonResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Padanan adm/myphp/sethakakses.php legacy.
 */
class UserController extends Controller
{
    public function index()
    {
        // Eager-load roles/permissions supaya hasRole()/hasPermissionTo() di
        // User::allowedRoles()/modulePermissions() tidak N+1 query per user
        // (Spatie loadMissing() otomatis pakai relasi ini kalau sudah
        // di-load, lihat vendor HasRoles::hasRole()/HasPermissions::hasDirectPermission()).
        // `person` di-eager-load buat avatar (PersonResource::avatar).
        return ApiResponse::success(PersonResource::collection(
            User::with(['prodi.fakultas', 'person', 'roles.permissions', 'permissions'])->orderBy('nama')->get()
        ));
    }

    public function updateRoles(UpdateUserRolesRequest $request, string $kodeperson)
    {
        // Lookup manual (bukan implicit route-model-binding) supaya user
        // dicari SETELAH middleware role:ADM/permission:ADM.manage-users di
        // atas ini sudah menggerbangi actor - implicit binding me-resolve
        // (dan 404-kan) model sebelum middleware sempat jalan, yang tadinya
        // membocorkan KODEPERSON mana yang valid ke siapa pun yang login
        // (lihat perbaikan serupa di AuthController::impersonate).
        $user = User::where('kodeperson', $kodeperson)->firstOrFail();

        $allowed = array_map(fn (string $r) => Role::from($r)->value, $request->input('allowedRoles'));

        // Permission CRUD granular per modul (per-user, bukan per-role), TAPI
        // dibatasi oleh hak akses default role tsb (role_has_permissions,
        // diatur lewat UI Manajemen Role). Modul yang tidak dikirim eksplisit
        // di `permissions` default ke seluruh aksi yang diizinkan role-nya.
        $submittedPermissions = $request->input('permissions', []);

        $flatPermissions = [];
        $warnings = [];

        foreach ($allowed as $roleValue) {
            $roleGrantedNames = SpatieRole::findOrCreate($roleValue, 'sanctum')
                ->permissions->pluck('name')
                ->all();

            // Aksi dianggap "diizinkan role" kalau SEMUA permission yang
            // digenggamnya sudah dipegang role ini (satu key aksi bisa
            // menggenggam >1 permission lintas resource - lihat
            // PermissionCatalog::permissionNamesForLegacyAction()).
            $roleAllowedActions = array_values(array_filter(
                PermissionCatalog::legacyActionKeysFor($roleValue),
                function (string $actionKey) use ($roleValue, $roleGrantedNames) {
                    $names = PermissionCatalog::permissionNamesForLegacyAction($roleValue, $actionKey);

                    return ! empty($names) && empty(array_diff($names, $roleGrantedNames));
                }
            ));

            $requestedActions = $submittedPermissions[$roleValue] ?? PermissionCatalog::legacyActionKeysFor($roleValue);

            $deniedActions = array_diff($requestedActions, $roleAllowedActions);
            $grantedActions = array_intersect($requestedActions, $roleAllowedActions);

            if (! empty($deniedActions)) {
                $warnings[] = sprintf(
                    'Peran %s belum diberi hak akses %s di Manajemen Role, sehingga tidak diterapkan ke pengguna ini.',
                    $roleValue,
                    implode(', ', $deniedActions)
                );
            }

            foreach ($grantedActions as $action) {
                array_push($flatPermissions, ...PermissionCatalog::permissionNamesForLegacyAction($roleValue, $action));
            }
        }

        $flatPermissions = array_values(array_unique($flatPermissions));

        // Tiga operasi tulis ini merepresentasikan satu perubahan RBAC yang
        // sama - dibungkus transaksi supaya tidak ada state user yang
        // setengah-jadi (mis. roles ke-update tapi permissions gagal).
        DB::transaction(function () use ($user, $allowed, $flatPermissions) {
            // Spatie (roles/model_has_roles) adalah sumber kebenaran RBAC v2.
            $user->syncRoles($allowed);

            // Tulis-ulang kolom legacy GROUPAKSES_* pada `person` terhubung
            // (satu arah) supaya app PHP lama tetap akurat - lihat
            // User::syncGroupAksesColumns().
            $user->syncGroupAksesColumns();

            $user->syncPermissions($flatPermissions);
        });

        // Legacy tidak punya kolom "peran utama" terpisah - urutan
        // Role::cases() menentukan modul aktif pertama (lihat User::defaultRole()),
        // jadi primaryRole dari request hanya dipakai untuk validasi input.

        return ApiResponse::success(
            empty($warnings) ? null : ['warnings' => $warnings],
            'Hak akses pengguna berhasil diperbarui'
        );
    }
}
