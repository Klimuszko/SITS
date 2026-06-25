<?php

namespace Database\Seeders;

use App\Enums\AssetFieldType;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Kategoria CMDB „Systemy / VM / Kontenery / Aplikacje” — wszystko logiczne:
 * systemy hostów, runtime, VM, kontenery, stacki, aplikacje, pakiety DSM,
 * usługi i bazy danych. Sprzęt fizyczny (NAS / Serwer / Komputer) pozostaje osobno.
 *
 * Uruchomienie (na serwerze, w kontenerze app):
 *   php artisan db:seed --class=Database\\Seeders\\SystemsVmContainersCategorySeeder
 *
 * Idempotentny: jeśli kategoria o tym kluczu już istnieje, nic nie robi.
 * Pola „relacja” renderowane jako tekst (typ relation nie jest jeszcze
 * obsługiwany w formularzu), „lista wielokrotna” jako zwykła lista (brak typu
 * multiselect). Po zaseedowaniu strukturę można dowolnie edytować w builderze.
 */
class SystemsVmContainersCategorySeeder extends Seeder
{
    private const KEY = 'systemy-vm-kontenery-aplikacje';

    /** Pola obowiązkowe (minimalny zestaw, by nie robić przerostu formy). */
    private const REQUIRED = [
        'Nazwa',
        'Typ elementu',
        'Warstwa',
        'Rola',
        'Status',
        'Krytyczność',
        'Uruchomione na / nadrzędny element',
        'Host fizyczny',
        'Platforma',
        'Backup aktywny',
        'Opis / uwagi',
    ];

    public function run(): void
    {
        if (AssetCategory::where('key', self::KEY)->exists()) {
            $this->command?->warn('Kategoria „'.self::KEY.'” już istnieje — pomijam seed.');

            return;
        }

        DB::transaction(function () {
            $category = AssetCategory::create([
                'name' => 'Systemy / VM / Kontenery / Aplikacje',
                'key' => self::KEY,
                'description' => 'Wszystko logiczne: systemy hostów, runtime, VM, kontenery, stacki, '
                    .'aplikacje, pakiety DSM, usługi i bazy danych. Sprzęt fizyczny pozostaje osobno (NAS / Serwer / Komputer).',
                'is_active' => true,
            ]);

            $usedFieldKeys = [];

            foreach ($this->definition() as $sectionOrder => $sec) {
                $section = AssetSection::create([
                    'asset_category_id' => $category->id,
                    'parent_id' => null,
                    'name' => $sec['name'],
                    'key' => Str::slug($sec['name']),
                    'is_group' => false,
                    'is_repeatable' => false,
                    'min_entries' => null,
                    'max_entries' => null,
                    'is_ticket_linkable' => false,
                    'display_field_id' => null,
                    'link_parent_on_select' => false,
                    'ticket_label' => null,
                    'order' => $sectionOrder,
                    'is_active' => true,
                ]);

                foreach ($sec['fields'] as $fieldOrder => $field) {
                    AssetField::create([
                        'asset_category_id' => $category->id,
                        'asset_section_id' => $section->id,
                        'name' => $field['name'],
                        'key' => $this->uniqueKey(Str::slug($field['name']), $usedFieldKeys),
                        'type' => $field['type'],
                        'options' => $field['options'] ?? null,
                        'placeholder' => null,
                        'default_value' => null,
                        'help' => $field['help'] ?? null,
                        'is_required' => in_array($field['name'], self::REQUIRED, true),
                        'order' => $fieldOrder,
                        'is_active' => true,
                    ]);
                }
            }

            $this->command?->info('Utworzono kategorię „'.$category->name.'” z 9 sekcjami i pełnym drzewem pól.');
        });
    }

    /** Unikalny klucz pola w obrębie kategorii (sufiks -2/-3 przy kolizji slugów). */
    private function uniqueKey(string $base, array &$used): string
    {
        $base = $base !== '' ? $base : 'pole';
        $key = $base;
        $n = 1;

        while (in_array($key, $used, true)) {
            $key = $base.'-'.(++$n);
        }

        $used[] = $key;

        return $key;
    }

    /**
     * Pełna definicja sekcji i pól. Helpery skracają zapis:
     *  text/textarea/ip/url/date — pola proste, select — lista z opcjami.
     *
     * @return array<int,array{name:string,fields:array<int,array<string,mixed>>}>
     */
    private function definition(): array
    {
        $text = fn (string $name, ?string $help = null) => ['name' => $name, 'type' => AssetFieldType::Text, 'help' => $help];
        $long = fn (string $name, ?string $help = null) => ['name' => $name, 'type' => AssetFieldType::Textarea, 'help' => $help];
        $date = fn (string $name) => ['name' => $name, 'type' => AssetFieldType::Date];
        $ip = fn (string $name) => ['name' => $name, 'type' => AssetFieldType::Ip];
        $url = fn (string $name, ?string $help = null) => ['name' => $name, 'type' => AssetFieldType::Url, 'help' => $help];
        $list = fn (string $name, array $options) => ['name' => $name, 'type' => AssetFieldType::Select, 'options' => $options];

        return [
            [
                'name' => 'Identyfikacja',
                'fields' => [
                    $text('Nazwa'),
                    $list('Typ elementu', [
                        'System hosta', 'Platforma / runtime', 'Maszyna wirtualna', 'Kontener',
                        'Stack / grupa kontenerów', 'Aplikacja', 'Pakiet DSM', 'Usługa systemowa',
                        'Baza danych', 'Inne',
                    ]),
                    $list('Warstwa', [
                        'System hosta', 'Runtime', 'VM', 'Kontener / stack', 'Aplikacja', 'Usługa pomocnicza',
                    ]),
                    $list('Rola', [
                        'Serwer aplikacji', 'Aplikacja biznesowa', 'Aplikacja inżynierska / CAD / CAE',
                        'Baza danych', 'Reverse proxy', 'DNS', 'VPN', 'Backup', 'Monitoring', 'Automatyzacja',
                        'Multimedia', 'Serwer plików', 'System zgłoszeń', 'System licencyjny', 'Inne',
                    ]),
                    $list('Status', [
                        'Produkcyjny', 'Testowy', 'Wyłączony', 'Do usunięcia', 'Archiwalny', 'Nieznany',
                    ]),
                    $list('Krytyczność', ['Wysoka', 'Średnia', 'Niska', 'Nieokreślona']),
                    $long('Opis / uwagi'),
                ],
            ],
            [
                'name' => 'Powiązanie / gdzie działa',
                'fields' => [
                    $text('Uruchomione na / nadrzędny element', 'np. Docker DSM, VM Debian-01, DSM Synology'),
                    $text('Host fizyczny', 'np. Synology DS923+, Dell R730, Lenovo Tiny'),
                    $text('Środowisko nadrzędne', 'np. Docker w DSM, Debian na Proxmox, aplikacja lokalna na VM'),
                    $text('Zależności', 'np. MariaDB, Traefik, serwer licencji, PostgreSQL'),
                ],
            ],
            [
                'name' => 'Platforma / system',
                'fields' => [
                    $list('Platforma', [
                        'Synology DSM', 'Synology Container Manager', 'Docker', 'Docker Compose', 'Podman',
                        'Proxmox', 'Hyper-V', 'VMware', 'Linux', 'Windows', 'Windows Server', 'Bare metal', 'Inne',
                    ]),
                    $text('System bazowy', 'np. DSM 7.2, Debian 12, Ubuntu 24.04, Windows Server 2022'),
                    $list('Runtime / silnik', [
                        'Nie dotyczy', 'Docker Engine', 'Synology Container Manager', 'Docker Compose', 'Podman',
                        'KVM / QEMU', 'Hyper-V', 'VMware ESXi', 'Inne',
                    ]),
                    $text('Wersja / tag', 'np. 2024 R2, latest, v3, 10.0.18, Debian 12'),
                    $list('Metoda aktualizacji', [
                        'Ręczna', 'Automatyczna', 'Package Center', 'APT', 'Windows Update', 'Watchtower',
                        'Aktualizacja obrazu Docker', 'Aktualizacja producenta', 'Nie aktualizować bez zgody', 'Nieznana',
                    ]),
                ],
            ],
            [
                'name' => 'Sieć i dostęp',
                'fields' => [
                    $ip('Adres IP'),
                    $list('Tryb sieci', [
                        'Nie dotyczy', 'LAN', 'NAT', 'Bridge', 'Host', 'Macvlan', 'Overlay', 'Reverse proxy', 'Inne',
                    ]),
                    $text('VLAN'),
                    $text('Porty', 'np. 80, 443, 3306, 8123'),
                    $url('URL / adres usługi', 'np. https://glpi.smart-dom.it'),
                    $list('Dostęp administracyjny', [
                        'Brak', 'WWW', 'SSH', 'RDP', 'DSM', 'Proxmox GUI', 'Portainer', 'VPN', 'Konsola lokalna', 'Inne',
                    ]),
                    $list('Dostęp z zewnątrz', [
                        'Brak', 'Tylko VPN', 'Reverse proxy', 'Publiczny', 'Ograniczony IP', 'Nieznany',
                    ]),
                ],
            ],
            [
                'name' => 'Instalacja / uruchomienie / dane',
                'fields' => [
                    $list('Sposób instalacji', [
                        'Nie dotyczy', 'Docker Compose', 'Pojedynczy kontener Docker', 'Stack', 'Pakiet DSM',
                        'Instalator producenta', 'Pakiet systemowy', 'APT', 'Manualnie', 'VM image', 'ISO', 'Inne',
                    ]),
                    $text('Obraz / pakiet / instalator', 'np. glpi/glpi, mariadb:11, ansys-2024R2-installer'),
                    $text('Ścieżka instalacji', 'np. /opt/ansys, C:\Program Files\App'),
                    $text('Ścieżka konfiguracji', 'np. /volume1/docker/glpi/docker-compose.yml'),
                    $text('Ścieżka danych', 'np. /volume1/docker/glpi/data'),
                    $long('Wolumeny / mapowania', 'np. /volume1/docker/glpi:/var/www/html'),
                    $list('Autostart', ['Tak', 'Nie', 'Ręcznie', 'Zależny od hosta', 'Nieznany']),
                ],
            ],
            [
                'name' => 'Zasoby',
                'fields' => [
                    $text('CPU / vCPU', 'np. 4 vCPU'),
                    $text('RAM', 'np. 8192 MB'),
                    $text('Dysk systemowy', 'np. 80 GB'),
                    $text('Dysk danych', 'np. 500 GB'),
                    $text('Datastore / wolumen', 'np. volume1, local-lvm, datastore01'),
                    $text('Limit zasobów', 'np. RAM limit 4 GB, CPU limit 2 cores'),
                ],
            ],
            [
                'name' => 'Backup',
                'fields' => [
                    $list('Backup aktywny', ['Tak', 'Nie', 'Częściowo', 'Do ustalenia', 'Nie dotyczy']),
                    $list('Typ backupu', [
                        'Brak', 'Pliki', 'Baza danych', 'Pliki + baza danych', 'Snapshot VM', 'Cała VM',
                        'Snapshot NAS', 'Konfiguracja', 'Dane użytkowników', 'Inne',
                    ]),
                    $long('Zakres backupu', 'np. katalog /volume1/docker/glpi + dump MariaDB'),
                    $text('Harmonogram', 'np. codziennie 22:00'),
                    $text('Retencja', 'np. 7 dni, 30 dni, 12 miesięcy'),
                    $text('Lokalizacja kopii', 'np. NAS backup, USB, chmura, drugi serwer'),
                    $date('Ostatni test odtworzenia'),
                ],
            ],
            [
                'name' => 'Licencja',
                'fields' => [
                    $list('Licencja wymagana', ['Tak', 'Nie', 'Open source', 'Do ustalenia', 'Nie dotyczy']),
                    $list('Typ licencji', [
                        'Nie dotyczy', 'Open source', 'Freeware', 'Per użytkownik', 'Per stanowisko',
                        'Per urządzenie', 'Sieciowa / floating', 'Subskrypcja', 'Wieczysta', 'Inne',
                    ]),
                    $date('Ważna do'),
                    $text('Serwer licencji', 'np. lic-srv-01, 1055@server'),
                    $long('Uwagi licencyjne'),
                ],
            ],
            [
                'name' => 'Bezpieczeństwo',
                'fields' => [
                    $list('Dane dostępowe', [
                        'Brak', 'Bitwarden', 'KeePass', '1Password', 'U klienta', 'Konto domenowe', 'Konto lokalne', 'Inne',
                    ]),
                    $text('Konto administracyjne', 'np. admin, root, administrator'),
                    $list('MFA', ['Tak', 'Nie', 'Nie dotyczy', 'Nieznane']),
                    $list('Dostęp tylko przez VPN', ['Tak', 'Nie', 'Częściowo', 'Nieznane']),
                    $list('Ekspozycja publiczna', [
                        'Brak', 'Reverse proxy', 'VPN', 'Publiczne', 'Ograniczone po IP', 'Nieznane',
                    ]),
                    $long('Uwagi bezpieczeństwa'),
                ],
            ],
        ];
    }
}
