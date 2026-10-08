<?php

namespace App\Enums;

/**
 * 6 aksi granular per modul, dipasangkan dengan App\Enums\Role untuk
 * membentuk nama permission Spatie "<MODUL>.<aksi>" (mis. "PEN.create").
 */
enum PermissionAction: string
{
    case CREATE = 'create';
    case READ = 'read';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case VALIDATE = 'validate';
    case OTHER = 'other';
}
