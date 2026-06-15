<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rejestr czasu pracy – opcjonalny, przypinany do ticketu lub pracy administracyjnej.
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users'); // support
            $table->nullableMorphs('timeloggable');             // ticket | administrative_work_log
            $table->string('description')->nullable();
            $table->unsignedInteger('minutes');
            $table->date('entry_date');
            $table->boolean('billable')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
