<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reserved = [
            ...array_map(fn (Role $r) => $r->value, Role::cases()),
            User::SUPER_ADMIN_ROLE,
        ];

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::notIn($reserved),
                Rule::unique('roles', 'name')->where('guard_name', 'sanctum'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.not_in' => 'Nama itu sudah dipakai peran bawaan sistem.',
        ];
    }
}
