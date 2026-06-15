<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('scope')->default('all');     // App\Enums\SupportScope
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['support_user_id', 'organization_id']);
            $table->index(['organization_id', 'is_primary']);
        });

        // Najwyżej jeden główny support na organizację (egzekwowane na poziomie bazy).
        DB::statement(
            'CREATE UNIQUE INDEX support_assignments_one_primary_per_org '.
            'ON support_assignments (organization_id) WHERE is_primary = true'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('support_assignments');
    }
};
