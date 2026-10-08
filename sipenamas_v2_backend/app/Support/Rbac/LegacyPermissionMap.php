<?php

namespace App\Support\Rbac;

/**
 * Peta permission generik lama (hasil cross-product Role x PermissionAction)
 * ke set permission granular baru dari PermissionCatalog. Dipakai bareng
 * oleh MigrateGranularPermissions (grant) dan DiffPermissionsSnapshot
 * (verify) supaya keduanya selalu konsisten satu sama lain.
 */
final class LegacyPermissionMap
{
    /** @var array<string, list<string>> permission lama => permission granular baru */
    public const MAP = [
        'PEN.update' => [
            'PEN.submit-revisi',
            'PEN.submit-monev',
            'PEN.submit-laporan-akhir',
        ],
        'ADM.update' => [
            'ADM.assign-reviewer',
            'ADM.decide-final-approval',
            'ADM.toggle-periode-aktif',
            'ADM.manage-users',
            'ADM.manage-roles',
        ],
        'ADM.read' => [
            'ADM.export-sinta',
        ],
    ];

    /** @return list<string> */
    public static function newPermissionsFor(string $oldPermission): array
    {
        return self::MAP[$oldPermission] ?? [];
    }
}
