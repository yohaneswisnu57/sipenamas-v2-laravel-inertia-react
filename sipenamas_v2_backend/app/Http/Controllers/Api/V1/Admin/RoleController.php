<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Matriks hak akses per Role x Resource x Aksi (role_has_permissions),
 * dipakai halaman Manajemen Role. Daftar role diambil LANGSUNG dari
 * tabel `roles` (guard sanctum) - bukan cuma 6 role modul bawaan
 * (`App\Enums\Role`) - supaya role custom yang dibuat admin lewat
 * store() ikut tampil & bisa diberi kombinasi permission apa saja dari
 * katalog universal (lihat PermissionCatalog). Role custom TIDAK
 * digerbangi middleware `role:` di manapun (cuma dapat akses data lewat
 * `permission:`), jadi tidak butuh perlakuan berbeda di sini.
 */
class RoleController extends Controller
{
    /** Role bawaan sistem - tidak boleh dihapus lewat destroy(). */
    private function protectedRoleNames(): array
    {
        return [
            ...array_map(fn (Role $r) => $r->value, Role::cases()),
            User::SUPER_ADMIN_ROLE,
        ];
    }

    public function index()
    {
        $allNames = PermissionCatalog::allPermissionNames();
        $labelByName = SpatiePermission::whereIn('name', $allNames)->pluck('label', 'name');
        $protectedNames = $this->protectedRoleNames();

        $roles = SpatieRole::where('guard_name', 'sanctum')
            ->orderBy('name')
            ->get()
            ->map(function (SpatieRole $spatieRole) use ($labelByName, $protectedNames) {
                $grantedNames = $spatieRole->permissions->pluck('name')->all();

                $resources = collect(PermissionCatalog::allResourceGroups())
                    ->map(function (array $group) use ($grantedNames, $labelByName) {
                        return [
                            'resource' => $group['resource'],
                            'actions' => collect($group['actions'])->map(fn (array $action) => [
                                'key' => $action['key'],
                                'permission' => $action['name'],
                                'label' => $labelByName[$action['name']] ?? PermissionCatalog::defaultLabelFor($action['name']),
                                'granted' => in_array($action['name'], $grantedNames, true),
                            ])->all(),
                        ];
                    })->all();

                return [
                    'role' => $spatieRole->name,
                    'isProtected' => in_array($spatieRole->name, $protectedNames, true),
                    'resources' => $resources,
                ];
            });

        return ApiResponse::success($roles);
    }

    public function store(StoreRoleRequest $request)
    {
        $spatieRole = SpatieRole::create([
            'name' => $request->validated('name'),
            'guard_name' => 'sanctum',
        ]);

        return ApiResponse::success([
            'role' => $spatieRole->name,
            'isProtected' => false,
        ], "Peran {$spatieRole->name} berhasil dibuat");
    }

    public function updatePermissions(UpdateRolePermissionsRequest $request, string $role)
    {
        $roleName = urldecode($role);
        if ($roleName === User::SUPER_ADMIN_ROLE || $role === User::SUPER_ADMIN_ROLE) {
            return ApiResponse::error('Hak akses peran Super Admin tidak dapat diubah.', 422);
        }

        $spatieRole = SpatieRole::where(function ($q) use ($role, $roleName) {
            $q->where('name', $role)->orWhere('name', $roleName);
        })->where('guard_name', 'sanctum')->firstOrFail();

        // Payload berisi nama permission asli (mis. "view penelitian"),
        // divalidasi terhadap katalog universal di UpdateRolePermissionsRequest
        // - berlaku sama untuk role bawaan maupun custom.
        $permissionNames = $request->input('permissions', []);

        $spatieRole->syncPermissions($permissionNames);

        return ApiResponse::success(null, "Hak akses untuk peran {$spatieRole->name} berhasil diperbarui");
    }

    public function destroy(string $role)
    {
        if (in_array($role, $this->protectedRoleNames(), true)) {
            return ApiResponse::error('Peran bawaan sistem tidak bisa dihapus', 422);
        }

        $spatieRole = SpatieRole::where('name', $role)->where('guard_name', 'sanctum')->firstOrFail();

        DB::transaction(function () use ($spatieRole) {
            $spatieRole->delete();
        });

        return ApiResponse::success(null, "Peran {$role} berhasil dihapus");
    }
}
