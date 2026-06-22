<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ikona (SVG) dla sekcji najwyższego poziomu = głównej kategorii zasobu,
 * pokazywana w bocznym menu kategorii w widoku zasobu. Migracja ADDYTYWNA.
 * Wartość to SANITYZOWANY SVG (App\Support\SvgSanitizer przy zapisie).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_sections', function (Blueprint $table) {
            $table->text('icon')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('asset_sections', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
