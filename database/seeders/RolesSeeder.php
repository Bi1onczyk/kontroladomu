<?php

namespace Database\Seeders;

use App\Domain\Accounts\Enums\UserRole;
use Illuminate\Database\Seeder;

/**
 * Tworzy role serwisu. Idempotentny: można uruchamiać wielokrotnie.
 */
class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        UserRole::ensureExist();
    }
}
