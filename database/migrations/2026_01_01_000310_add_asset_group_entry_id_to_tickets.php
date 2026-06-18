<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 14c — pod-zasoby w zgłoszeniach.
 *
 * Dodatkowa, w pełni opcjonalna kolumna FK wskazująca na wpis grupy powtarzalnej
 * (asset_group_entries) = pod-zasób, z którym powiązano zgłoszenie. Nie narusza
 * istniejących indeksów/unique (bezpieczna przebudowa na SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('asset_group_entry_id')->nullable()->after('asset_id')
                ->constrained('asset_group_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_group_entry_id');
        });
    }
};
