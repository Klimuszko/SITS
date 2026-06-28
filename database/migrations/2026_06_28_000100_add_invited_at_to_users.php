<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zarządzanie zaproszeniami (Step 22). Migracja ADDYTYWNA: jedna nowa, nullowalna
 * kolumna znacznika czasu zaproszenia. Stan „oczekujące zaproszenie" trzymamy
 * JAWNIE na koncie (z tabeli tokenów nie da się odróżnić zaproszenia od resetu
 * hasła — ten sam broker/tabela). Ustawiane przy zaproszeniu, czyszczone (→ null)
 * przy aktywacji (ustawienie hasła lub pierwsze logowanie SSO). Bez ruszania
 * istniejących indeksów (sqlite-safe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable()->after('oauth_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('invited_at');
        });
    }
};
