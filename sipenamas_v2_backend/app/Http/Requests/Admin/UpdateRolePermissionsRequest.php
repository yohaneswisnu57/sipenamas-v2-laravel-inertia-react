<?php

namespace App\Http\Requests\Admin;

use App\Support\Rbac\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Katalog permission sekarang universal (tidak lagi dibatasi per
        // role) - role APAPUN (fixed atau custom) boleh diberi kombinasi
        // permission apa saja dari katalog ini.
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::allPermissionNames())],
        ];
    }
}
