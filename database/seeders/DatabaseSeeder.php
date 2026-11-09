<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,           // fake test accounts (skipped in production)
            ReferenceDataSeeder::class,  // programs, subjects, requirements, settings
        ]);
    }
}
