<?php

namespace Database\Seeders;

use App\Models\TicketPriority;
use Illuminate\Database\Seeder;

class TicketPrioritySeeder extends Seeder
{
    public function run(): void
    {
        $priorities = [
            ['name' => 'Niski', 'level' => 1, 'color' => 'gray'],
            ['name' => 'Normalny', 'level' => 2, 'color' => 'blue'],
            ['name' => 'Wysoki', 'level' => 3, 'color' => 'amber'],
            ['name' => 'Krytyczny', 'level' => 4, 'color' => 'red'],
        ];

        foreach ($priorities as $priority) {
            TicketPriority::updateOrCreate(
                ['level' => $priority['level']],
                $priority,
            );
        }
    }
}
