<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('color', 16)->default('#fef3c7');
            $table->boolean('pinned')->default(false);
            $table->timestamp('pinned_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'pinned', 'pinned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
