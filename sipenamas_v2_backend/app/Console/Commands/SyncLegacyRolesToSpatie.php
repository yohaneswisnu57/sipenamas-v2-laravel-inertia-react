<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Person;
use Illuminate\Console\Command;

/**
 * Migrasi satu kali: baca hak akses modul dari kolom legacy GROUPAKSES_*
 * (dan ISSUPERUSER) pada tabel `person`, lalu tulis ke tabel Spatie
 * (roles/model_has_roles) lewat syncRoles(). Jalankan sekali setelah
 * migrasi + RoleSeeder, sebelum UserController dipindah memakai Spatie,
 * supaya tidak ada user yang kehilangan akses saat cutover.
 */
class SyncLegacyRolesToSpatie extends Command
{
    protected $signature = 'rbac:backfill';

    protected $description = 'Backfill role Spatie dari kolom legacy GROUPAKSES_* pada tabel person';

    public function handle(): int
    {
        $people = Person::all();

        $bar = $this->output->createProgressBar($people->count());
        $bar->start();

        foreach ($people as $person) {
            $roles = $person->ISSUPERUSER
                ? Role::cases()
                : array_values(array_filter(
                    Role::cases(),
                    fn (Role $role) => filled($person->{$role->groupAksesColumn()})
                ));

            $person->syncRoles(array_map(fn (Role $role) => $role->value, $roles));

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info(sprintf('Selesai. %d person diproses.', $people->count()));

        return self::SUCCESS;
    }
}
