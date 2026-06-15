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
        ]);
    }
}
