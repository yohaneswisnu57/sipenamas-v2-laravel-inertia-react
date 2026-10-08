<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allowedRoles' => ['required', 'array', 'min:1'],
            'allowedRoles.*' => [new Enum(Role::class)],
            'primaryRole' => ['nullable', new Enum(Role::class)],

            // Permission granular per modul, mis. {"PEN": ["create","submit-revisi"]}.
            // Aksi valid berbeda per modul (lihat PermissionCatalog), jadi tidak
            // bisa divalidasi statis di sini - lihat withValidator().
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string'],
        ];
    }

    /**
     * Pastikan key di `permissions` (kode modul) hanya untuk modul yang
     * juga ada di `allowedRoles` - tidak masuk akal memberi permission
     * untuk modul yang tidak diizinkan sama sekali bagi user ini. Sekaligus
     * validasi tiap aksi terhadap daftar aksi valid modul tsb (PermissionCatalog),
     * karena aksi per modul tidak lagi seragam.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $allowedRoles = $this->input('allowedRoles', []);
            $permissions = $this->input('permissions', []);

            $unknown = array_diff(array_keys($permissions), $allowedRoles);

            if (! empty($unknown)) {
                $validator->errors()->add(
                    'permissions',
                    'Modul permission ('.implode(', ', $unknown).') harus ada di allowedRoles.'
                );
            }

            foreach ($permissions as $module => $actions) {
                $invalid = array_diff((array) $actions, PermissionCatalog::legacyActionKeysFor($module));

                if (! empty($invalid)) {
                    $validator->errors()->add(
                        "permissions.{$module}",
                        'Aksi ('.implode(', ', $invalid).") tidak valid untuk modul {$module}."
                    );
                }
            }
        });
    }
}
