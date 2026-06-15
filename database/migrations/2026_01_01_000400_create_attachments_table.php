<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polimorficzne załączniki z kontrolą dostępu per organizacja.
        // Pliki trzymane na dysku prywatnym, pobierane wyłącznie przez kontroler.
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->nullableMorphs('attachable'); // ticket, comment, asset, article, work_log, organization
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
