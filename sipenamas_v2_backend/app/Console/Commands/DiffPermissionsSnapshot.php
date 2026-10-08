<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Support\Rbac\LegacyPermissionMap;
use Illuminate\Console\Command;

/**
 * Alat verifikasi migrasi permission granular (Deploy A, langkah 3 & 5 di
 * plan). `snapshot` merekam permission efektif tiap Person SEBELUM migrasi;
 * `verify` mengecek state SEKARANG terhadap snapshot itu, memastikan setiap
 * permission lama yang dulu dimiliki sudah punya seluruh padanan granular
 * barunya (LegacyPermissionMap), dan setiap eks-ISSUPERUSER sudah punya
 * role Super Admin. Wajib dijalankan di staging (dump production) dulu,
 * baru di production sebelum lanjut ke Deploy B.
 */
class DiffPermissionsSnapshot extends Command
{
    protected $signature = 'rbac:permissions-snapshot {mode : snapshot|verify} {--file=pre-migration.json}';

    protected $description = 'Rekam atau verifikasi permission efektif tiap Person, untuk memastikan migrasi permission granular tidak menghilangkan akses siapa pun';

    public function handle(): int
    {
        $mode = $this->argument('mode');
        $path = storage_path('app/rbac-snapshots/'.$this->option('file'));

        if (! in_array($mode, ['snapshot', 'verify'], true)) {
            $this->error("Mode tidak dikenal: {$mode}. Gunakan 'snapshot' atau 'verify'.");

            return self::FAILURE;
        }

        return $mode === 'snapshot' ? $this->snapshot($path) : $this->verify($path);
    }

    private function snapshot(string $path): int
    {
        $data = Person::all()->map(fn (Person $person) => [
            'kodeperson' => $person->KODEPERSON,
            'is_superuser' => (bool) $person->ISSUPERUSER,
            'permissions' => $person->getAllPermissions()->pluck('name')->sort()->values()->all(),
        ]);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $data->toJson(JSON_PRETTY_PRINT));

        $this->info(sprintf('Snapshot %d person disimpan ke %s', $data->count(), $path));

        return self::SUCCESS;
    }

    private function verify(string $path): int
    {
        if (! file_exists($path)) {
            $this->error("File snapshot tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $snapshot = collect(json_decode(file_get_contents($path), true))
            ->keyBy('kodeperson');

        $regressions = [];

        Person::all()->each(function (Person $person) use ($snapshot, &$regressions) {
            $before = $snapshot->get($person->KODEPERSON);

            if (! $before) {
                return;
            }

            $currentPermissions = $person->getAllPermissions()->pluck('name')->all();
            $missing = [];

            foreach ($before['permissions'] as $oldName) {
                foreach (LegacyPermissionMap::newPermissionsFor($oldName) as $newName) {
                    if (! in_array($newName, $currentPermissions, true)) {
                        $missing[] = $newName;
                    }
                }
            }

            if ($before['is_superuser'] && ! $person->hasRole(Person::SUPER_ADMIN_ROLE)) {
                $missing[] = 'role:'.Person::SUPER_ADMIN_ROLE;
            }

            if (! empty($missing)) {
                $regressions[$person->KODEPERSON] = array_unique($missing);
            }
        });

        if (empty($regressions)) {
            $this->info('Tidak ada regresi akses. Aman lanjut ke Deploy B.');

            return self::SUCCESS;
        }

        $this->error(sprintf('%d person kehilangan akses:', count($regressions)));

        foreach ($regressions as $kodeperson => $missing) {
            $this->line("  {$kodeperson}: ".implode(', ', $missing));
        }

        return self::FAILURE;
    }
}
