<?php

namespace App\Console\Commands;

use App\Enums\PermissionAction;
use App\Models\Person;
use Illuminate\Console\Command;

/**
 * Migrasi satu kali: sebelum ini, setiap pemegang role modul otomatis
 * punya akses penuh (tidak ada granularitas CRUD). Supaya tidak ada yang
 * kehilangan akses saat permission mulai ditegakkan di routes/api.php,
 * beri setiap person permission CRUD penuh untuk setiap role modul yang
 * SUDAH mereka miliki saat ini. Admin bisa mempersempit lewat UI
 * Manajemen User sesudah ini. Idempotent - aman dijalankan ulang.
 */
class GrantDefaultModulePermissions extends Command
{
    protected $signature = 'rbac:backfill-permissions';

    protected $description = 'Beri setiap person permission CRUD penuh untuk role modul yang sudah dimiliki saat ini';

    public function handle(): int
    {
        $people = Person::all();

        $bar = $this->output->createProgressBar($people->count());
        $bar->start();

        foreach ($people as $person) {
            $roles = $person->getRoleNames();

            $permissions = [];
            foreach ($roles as $roleValue) {
                foreach (PermissionAction::cases() as $action) {
                    $permissions[] = "{$roleValue}.{$action->value}";
                }
            }

            $person->syncPermissions($permissions);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info(sprintf('Selesai. %d person diproses.', $people->count()));

        return self::SUCCESS;
    }
}
