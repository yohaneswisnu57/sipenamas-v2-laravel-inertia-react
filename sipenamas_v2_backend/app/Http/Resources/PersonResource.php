<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nama class dipertahankan "PersonResource" demi kompatibilitas kontrak
 * JSON yang sudah dipakai frontend (sipenamas_v2_frontend), tapi sumbernya
 * sekarang App\Models\User (identitas login + RBAC), BUKAN App\Models\Person
 * lagi. `avatar` diambil dari relasi `person` (data histori dosen) sebagai
 * fallback tampilan, kalau ada.
 */
class PersonResource extends JsonResource
{
    private ?Role $activeRole = null;

    public static function withActiveRole(User $user, Role $activeRole): self
    {
        $resource = new self($user);
        $resource->activeRole = $activeRole;

        return $resource;
    }

    public function toArray(Request $request): array
    {
        $allowedRoles = $this->resource->allowedRoles();
        $activeRole = ($this->activeRole ?? $allowedRoles[0] ?? null)?->value;

        return [
            'id' => $this->kodeperson,
            'kodeperson' => $this->kodeperson,
            'name' => $this->nama,
            'email' => $this->email,
            'nidn' => $this->nidn,
            'prodi' => $this->prodi?->NAMAPRODI,
            'fakultas' => $this->prodi?->fakultas?->NAMAFAKULTAS,
            'role' => $activeRole,
            'activeRole' => $activeRole,
            'allowedRoles' => array_map(fn (Role $r) => $r->value, $allowedRoles),
            'modulePermissions' => $this->resource->modulePermissions($allowedRoles),
            'avatar' => $this->resource->person?->URL_FOTO ?: null,
            'isExternal' => (bool) $this->is_external,
            'isSuperAdmin' => $this->resource->hasRole(User::SUPER_ADMIN_ROLE),
        ];
    }
}
