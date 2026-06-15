<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minecraft_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('minecraft_server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('uuid', 36);
            $table->string('name');
            $table->string('ip', 45)->nullable();
            $table->boolean('online')->default(false);
            $table->unsignedInteger('join_count')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'uuid']);
            $table->index(['project_id', 'online']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minecraft_players');
    }
};
