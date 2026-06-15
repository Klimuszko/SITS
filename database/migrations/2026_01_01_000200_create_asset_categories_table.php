<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Globalne kategorie zasobów (zarządzane przez admina).
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();        // klucz techniczny / slug
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);  // dezaktywacja
            $table->timestamps();
            $table->softDeletes();                        // archiwizacja
        });

        // Sekcje/zakładki w obrębie kategorii (jak w GLPI).
        Schema::create('asset_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['asset_category_id', 'key']);
        });

        // Definicje dynamicznych pól.
        Schema::create('asset_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_section_id')->nullable()
                ->constrained('asset_sections')->nullOnDelete();
            $table->string('name');
            $table->string('key');                  // klucz techniczny
            $table->string('type')->default('text'); // App\Enums\AssetFieldType
            $table->json('options')->nullable();     // dla typu select
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['asset_category_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_fields');
        Schema::dropIfExists('asset_sections');
        Schema::dropIfExists('asset_categories');
    }
};
