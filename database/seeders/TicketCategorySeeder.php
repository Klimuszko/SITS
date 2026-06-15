<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TicketCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Awaria', 'Pytanie', 'Zmiana', 'Nowe konto', 'Dostęp', 'Sprzęt',
            'Oprogramowanie', 'Sieć', 'Backup', 'Microsoft 365', 'Drukarka', 'Inne',
        ];

        foreach ($categories as $name) {
            TicketCategory::updateOrCreate(
                ['key' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );
        }
    }
}
