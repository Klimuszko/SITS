<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logowanie SSO (Microsoft/Google). Migracja ADDYTYWNA: tylko nowe, nullowalne
 * kolumny powiązania z dostawcą. Hasło zostaje NOT NULL — konta SSO/zaproszone
 * dostają losowy, nieużywalny placeholder (prawdziwe hasło ustawia użytkownik
 * przez link „ustaw hasło"); dzięki temu nie ruszamy istniejącej kolumny (sqlite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('oauth_provider')->nullable()->after('password'); // 'microsoft' | 'google'
            $table->string('oauth_id')->nullable()->after('oauth_provider'); // id konta u dostawcy
            $table->index(['oauth_provider', 'oauth_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['oauth_provider', 'oauth_id']);
            $table->dropColumn(['oauth_provider', 'oauth_id']);
        });
    }
};
