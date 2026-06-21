<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TicketPrioritySeeder::class,
            TicketCategorySeeder::class,
            AssetCategorySeeder::class,
            SuperAdminSeeder::class,
            DemoDataSeeder::class,
            // Na końcu: tworzy profile systemowe i backfilluje istniejących
            // użytkowników/członkostwa (z poprzednich seederów) ich profilami.
            AccessProfileSeeder::class,
        ]);
    }
}
