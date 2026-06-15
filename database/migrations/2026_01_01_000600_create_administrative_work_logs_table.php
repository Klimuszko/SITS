<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()
                ->constrained('locations')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()
                ->constrained('assets')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('work_type')->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamp('performed_at');
            $table->unsignedInteger('duration_minutes')->nullable(); // czas pracy (opcjonalny)
            $table->boolean('visible_to_manager')->default(true);
            $table->boolean('visible_to_user')->default(false);
            $table->string('status')->default('published'); // App\Enums\PublicationStatus
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_work_logs');
    }
};
