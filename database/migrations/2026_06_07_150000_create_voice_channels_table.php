<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('rank_id')->nullable()->constrained('ranks')->nullOnDelete();
            $table->string('name');
            $table->boolean('with_video')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'position']);
        });

        Schema::create('voice_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voice_channel_id')->constrained('voice_channels')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique('user_id'); // un seul salon vocal actif par utilisateur
            $table->index('voice_channel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_participants');
        Schema::dropIfExists('voice_channels');
    }
};
