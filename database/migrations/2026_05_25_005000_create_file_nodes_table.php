<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('file_nodes')->cascadeOnDelete();
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['folder', 'file']);
            $table->string('name');
            $table->string('path')->nullable();
            $table->string('mime', 191)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_nodes');
    }
};
