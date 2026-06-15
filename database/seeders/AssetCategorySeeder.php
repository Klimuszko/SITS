<?php

namespace Database\Seeders;

use App\Enums\AssetFieldType;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AssetCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'NAS', 'Serwer', 'Komputer', 'Laptop', 'Telefon', 'Router', 'Switch',
            'Access Point', 'Maszyna wirtualna', 'Kontener', 'Aplikacja customowa',
            'Usługa', 'Licencja', 'UPS', 'Domena', 'Backup',
        ];

        $created = [];
        foreach ($categories as $name) {
            $created[$name] = AssetCategory::updateOrCreate(
                ['key' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );
        }

        $this->seedNasTemplate($created['NAS']);
    }

    /** Przykładowy szablon (sekcje + pola dynamiczne) dla kategorii NAS (§14). */
    protected function seedNasTemplate(AssetCategory $nas): void
    {
        $sections = [
            'general' => 'Informacje ogólne',
            'disks' => 'Dyski',
            'memory' => 'RAM',
            'network' => 'Sieć',
            'backup' => 'Backup',
            'notes' => 'Notatki',
        ];

        $sectionModels = [];
        $order = 0;
        foreach ($sections as $key => $name) {
            $sectionModels[$key] = AssetSection::updateOrCreate(
                ['asset_category_id' => $nas->id, 'key' => $key],
                ['name' => $name, 'order' => $order++, 'is_active' => true],
            );
        }

        $fields = [
            ['general', 'producent', 'Producent', AssetFieldType::Text, false],
            ['general', 'model', 'Model', AssetFieldType::Text, false],
            ['general', 'numer_seryjny', 'Numer seryjny', AssetFieldType::Text, false],
            ['general', 'ip', 'Adres IP', AssetFieldType::Text, false],
            ['general', 'hostname', 'Hostname', AssetFieldType::Text, false],
            ['general', 'firmware', 'Firmware', AssetFieldType::Text, false],
            ['disks', 'liczba_dyskow', 'Liczba dysków', AssetFieldType::Number, false],
            ['disks', 'raid', 'RAID', AssetFieldType::Select, false, ['RAID 0', 'RAID 1', 'RAID 5', 'RAID 6', 'RAID 10']],
            ['memory', 'ram', 'RAM (GB)', AssetFieldType::Number, false],
            ['backup', 'status_backupu', 'Status backupu', AssetFieldType::Select, false, ['OK', 'Ostrzeżenie', 'Błąd', 'Brak']],
            ['general', 'data_gwarancji', 'Data gwarancji', AssetFieldType::Date, false],
        ];

        $order = 0;
        foreach ($fields as $field) {
            [$section, $key, $name, $type, $required] = $field;
            $options = $field[5] ?? null;

            AssetField::updateOrCreate(
                ['asset_category_id' => $nas->id, 'key' => $key],
                [
                    'asset_section_id' => $sectionModels[$section]->id,
                    'name' => $name,
                    'type' => $type->value,
                    'options' => $options,
                    'is_required' => $required,
                    'order' => $order++,
                    'is_active' => true,
                ],
            );
        }
    }
}
