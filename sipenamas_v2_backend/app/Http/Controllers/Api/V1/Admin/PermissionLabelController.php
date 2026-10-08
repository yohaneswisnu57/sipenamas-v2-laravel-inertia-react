<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePermissionLabelRequest;
use App\Support\ApiResponse;
use App\Support\Rbac\PermissionCatalog;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Halaman "Manajemen Permission" - HANYA mengedit label/deskripsi
 * tampilan permission yang sudah ada (dari PermissionSeeder). Sengaja
 * TIDAK ada store()/destroy(): daftar resource & aksi yang benar-benar
 * berlaku ditentukan developer di
 * App\Support\Rbac\PermissionCatalog::RESOURCE_PERMISSIONS, membuat/
 * menghapus permission lewat UI berisiko menciptakan permission yang
 * tidak pernah dicek kode manapun.
 *
 * Katalog permission bersifat UNIVERSAL (tidak terikat role), jadi
 * ditampilkan sebagai SATU daftar resource -> aksi - bukan lagi
 * dikelompokkan ulang per role seperti sebelumnya.
 */
class PermissionLabelController extends Controller
{
    public function index()
    {
        $byName = SpatiePermission::whereIn('name', PermissionCatalog::allPermissionNames())
            ->get(['id', 'name', 'label', 'description'])
            ->keyBy('name');

        $resources = collect(PermissionCatalog::allResourceGroups())
            ->map(function (array $group) use ($byName) {
                return [
                    'resource' => $group['resource'],
                    'actions' => collect($group['actions'])->map(function (array $action) use ($byName) {
                        $permission = $byName->get($action['name']);

                        return [
                            'id' => $permission?->id,
                            'action' => $action['key'],
                            'permission' => $action['name'],
                            'label' => $permission?->label,
                            'description' => $permission?->description,
                        ];
                    })->all(),
                ];
            })->all();

        return ApiResponse::success($resources);
    }

    public function update(UpdatePermissionLabelRequest $request, SpatiePermission $permission)
    {
        $permission->update($request->only('label', 'description'));

        return ApiResponse::success([
            'id' => $permission->id,
            'name' => $permission->name,
            'label' => $permission->label,
            'description' => $permission->description,
        ], 'Label permission berhasil diperbarui');
    }
}
