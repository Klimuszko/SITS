<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile dostępu (RBAC) — warstwa „CO" modelu uprawnień. Migracja ADDYTYWNA:
 * nowa tabela, nic nie rusza w istniejących. Uprawnienia trzymane jako lista
 * kluczy w kolumnie JSON (katalog kluczy żyje w App\Enums\Permission).
 *
 * applies_to: 'staff' (profil globalny personelu) | 'client' (profil per organizacja).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // stabilny klucz (np. 'support'); systemowe + własne
            $table->string('name');
            $table->string('applies_to')->default('staff');
            $table->boolean('is_system')->default(false);   // profil systemowy — nieusuwalny
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable();        // lista kluczy uprawnień
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_profiles');
    }
};
