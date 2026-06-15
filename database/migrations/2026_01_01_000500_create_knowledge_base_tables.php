<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()
                ->constrained('knowledge_categories')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');                  // HTML sanityzowany (HTMLPurifier)
            $table->foreignId('knowledge_category_id')->nullable()
                ->constrained('knowledge_categories')->nullOnDelete();
            $table->string('status')->default('draft')->index(); // App\Enums\PublicationStatus
            $table->foreignId('author_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Elastyczna, wielowyborowa widoczność artykułu (§22).
        Schema::create('knowledge_article_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_article_id')->constrained()->cascadeOnDelete();
            $table->string('visibility_type'); // organization | group | role | user
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('role')->nullable(); // App\Enums\Role (support|manager|user|...)
            $table->timestamps();

            $table->index(['knowledge_article_id', 'visibility_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_article_visibility');
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('knowledge_categories');
    }
};
