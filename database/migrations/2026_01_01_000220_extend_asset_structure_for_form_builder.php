<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Krok 14a — warstwa DEFINICJI generycznego buildera formularzy zasobów.
 *
 * Migracja ADDYTYWNA: same NOWE, NULLOWALNE kolumny + DWIE nowe tabele.
 * Nic nie zmienia ani nie usuwa w istniejących tabelach (w szczególności
 * NIE rusza unikalnego indeksu `asset_field_values(asset_id, asset_field_id)`
 * — sqlite przebudowałby tabelę = ryzyko CI). Istniejące „płaskie” kategorie
 * działają bez zmian: zwykła sekcja = parent_id NULL, is_group=false.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Sekcje stają się węzłami drzewa: Sekcja | Podsekcja | Grupa powtarzalna.
        Schema::table('asset_sections', function (Blueprint $table) {
            // Zagnieżdżanie — rodzic to inna sekcja tej samej kategorii.
            $table->foreignId('parent_id')->nullable()->after('asset_category_id')
                ->constrained('asset_sections')->nullOnDelete();
            // Czy węzeł jest grupą (kontener dla podsekcji/grup powtarzalnych).
            $table->boolean('is_group')->default(false)->after('key');
            // Czy grupa jest powtarzalna (wiele wpisów = kandydaci na pod-zasoby).
            $table->boolean('is_repeatable')->default(false)->after('is_group');
            $table->unsignedInteger('min_entries')->nullable()->after('is_repeatable');
            $table->unsignedInteger('max_entries')->nullable()->after('min_entries');
            // Konfiguracja pod-zasobu w zgłoszeniach (wykorzystywana w 14c).
            $table->boolean('is_ticket_linkable')->default(false)->after('max_entries');
            // Pole, którego wartość etykietuje pojedynczy wpis (pod-zasób).
            $table->foreignId('display_field_id')->nullable()->after('is_ticket_linkable')
                ->constrained('asset_fields')->nullOnDelete();
            // Czy wybranie pod-zasobu w zgłoszeniu linkuje też zasób-rodzica.
            $table->boolean('link_parent_on_select')->default(false)->after('display_field_id');
            // Etykieta prezentowana w kontekście zgłoszenia.
            $table->string('ticket_label')->nullable()->after('link_parent_on_select');
        });

        // 2) Pola zyskują metadane prezentacji (renderowanie w 14b).
        Schema::table('asset_fields', function (Blueprint $table) {
            $table->string('placeholder')->nullable()->after('options');
            $table->text('default_value')->nullable()->after('placeholder');
            $table->string('help')->nullable()->after('default_value');
        });

        // 3) Wpisy grup powtarzalnych — jeden wiersz = jedna instancja grupy
        //    (kandydat na pod-zasób). Zagnieżdżanie przez parent_entry_id.
        Schema::create('asset_group_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_section_id')->constrained('asset_sections')->cascadeOnDelete();
            $table->foreignId('parent_entry_id')->nullable()
                ->constrained('asset_group_entries')->nullOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        // 4) Wartości pól per wpis grupy. Osobna tabela (NIE asset_field_values),
        //    bo pole powtarzalne może mieć wiele wartości — po jednej na wpis.
        Schema::create('asset_group_entry_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_group_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_field_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['asset_group_entry_id', 'asset_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_group_entry_values');
        Schema::dropIfExists('asset_group_entries');

        Schema::table('asset_fields', function (Blueprint $table) {
            $table->dropColumn(['placeholder', 'default_value', 'help']);
        });

        Schema::table('asset_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('display_field_id');
            $table->dropColumn([
                'is_group', 'is_repeatable', 'min_entries', 'max_entries',
                'is_ticket_linkable', 'link_parent_on_select', 'ticket_label',
            ]);
        });
    }
};
