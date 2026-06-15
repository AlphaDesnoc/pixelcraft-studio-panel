<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minecraft_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Serveur Minecraft');
            $table->string('token', 64)->unique();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minecraft_servers');
    }
};
