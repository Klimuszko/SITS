<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Podpięcie profili dostępu. ADDYTYWNE, NULLOWALNE kolumny:
 *  - users.access_profile_id           → globalny profil personelu,
 *  - organization_memberships.access_profile_id → profil klienta w danej organizacji.
 *
 * Nullable + nullOnDelete: dopóki nic ich nie czyta (krok A1), zachowanie aplikacji
 * jest niezmienione; backfill robi AccessProfileSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('access_profile_id')->nullable()->after('role')
                ->constrained('access_profiles')->nullOnDelete();
        });

        Schema::table('organization_memberships', function (Blueprint $table) {
            $table->foreignId('access_profile_id')->nullable()->after('role')
                ->constrained('access_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_profile_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_profile_id');
        });
    }
};
