<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('company');          // App\Enums\OrganizationType
            $table->foreignId('parent_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->string('status')->default('active')->index(); // App\Enums\OrganizationStatus
            $table->string('nip')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('internal_note')->nullable();            // notatka dla supportu/admina
            // Domyślny (główny) support – wymagany dla aktywnej organizacji (egzekwowane w aplikacji).
            $table->foreignId('default_support_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
