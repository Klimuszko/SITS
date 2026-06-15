<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Globalne kategorie ticketów (zarządzane przez admina).
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Globalne priorytety ticketów (tabela – pod przyszłe SLA).
        Schema::create('ticket_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('level')->default(2); // 1=niski ... 4=krytyczny
            $table->string('color')->default('gray');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();              // np. T-2026-000001
            $table->string('title');
            $table->text('description');
            $table->foreignId('requester_id')->constrained('users');     // zgłaszający
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('location_id')->nullable()
                ->constrained('locations')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()
                ->constrained('assets')->nullOnDelete();
            $table->foreignId('assigned_support_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('status')->default('new')->index(); // App\Enums\TicketStatus
            $table->foreignId('ticket_priority_id')->nullable()
                ->constrained('ticket_priorities')->nullOnDelete();
            $table->foreignId('ticket_category_id')->nullable()
                ->constrained('ticket_categories')->nullOnDelete();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['assigned_support_id', 'status']);
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('type')->default('public'); // App\Enums\CommentType
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ticket_id', 'type']);
        });

        Schema::create('ticket_observers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_observers');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_priorities');
        Schema::dropIfExists('ticket_categories');
    }
};
