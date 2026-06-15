<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('user');           // App\Enums\OrgRole (user|manager)
            $table->string('manager_scope')->nullable();        // App\Enums\ManagerScope (tylko dla managera)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'organization_id']);
            $table->index(['organization_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_memberships');
    }
};
