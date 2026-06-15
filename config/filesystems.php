<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        // Dysk prywatny – załączniki. Pobieranie WYŁĄCZNIE przez kontroler
        // z autoryzacją (Policy). Nigdy nie wystawiamy tych plików publicznie.
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
        ],

        // Pliki publiczne (np. logo) – opcjonalne, nie dla załączników klientów.
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
