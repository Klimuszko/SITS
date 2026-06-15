<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()
                ->constrained('locations')->nullOnDelete();
            $table->foreignId('asset_category_id')->constrained();
            // Rodzic techniczny (NAS -> Linux VM -> Ansys Server).
            $table->foreignId('parent_asset_id')->nullable()
                ->constrained('assets')->nullOnDelete();
            $table->string('name');
            $table->string('inventory_code')->nullable();
            $table->string('status')->default('active')->index(); // App\Enums\AssetStatus
            $table->boolean('is_private')->default(false);        // zasób prywatny/indywidualny
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'asset_category_id']);
            $table->index(['organization_id', 'is_private']);
        });

        // Wartości dynamicznych pól.
        Schema::create('asset_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_field_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();   // wartość surowa; rzutowana wg typu pola
            $table->timestamps();

            $table->unique(['asset_id', 'asset_field_id']);
        });

        // Relacje/zależności między zasobami (wiele typów).
        Schema::create('asset_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('type')->default('related_to'); // App\Enums\AssetRelationType
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'related_asset_id', 'type']);
        });

        // Przypisanie zasobu do użytkowników (zasoby prywatne / indywidualne).
        Schema::create('asset_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['asset_id', 'user_id']);
        });

        // Historia zmian zasobu.
        Schema::create('asset_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');                 // created, field_updated, location_changed...
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['asset_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_history');
        Schema::dropIfExists('asset_user');
        Schema::dropIfExists('asset_relations');
        Schema::dropIfExists('asset_field_values');
        Schema::dropIfExists('assets');
    }
};
