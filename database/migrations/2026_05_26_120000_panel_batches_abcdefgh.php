<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direct_messages', function (Blueprint $table) {
            $table->json('mentions')->nullable()->after('body');
            $table->foreignId('reply_to_id')->nullable()->after('mentions')
                ->constrained('direct_messages')->nullOnDelete();
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('reply_to_id')->nullable()->after('mentions')
                ->constrained('chat_messages')->nullOnDelete();
        });

        Schema::create('chat_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();
            $table->unique(['chat_message_id', 'user_id', 'emoji']);
        });

        Schema::create('task_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('color', 16)->default('#7c5cff');
            $table->timestamps();
            $table->unique(['project_id', 'name']);
        });

        Schema::create('task_tag', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('task_tag_id')->constrained('task_tags')->cascadeOnDelete();
            $table->primary(['task_id', 'task_tag_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('completed_at');
        });

        Schema::table('bugs', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->after('assigned_rank_id')
                ->constrained('tasks')->nullOnDelete();
            $table->timestamp('sla_due_at')->nullable()->after('status');
        });

        Schema::table('project_user', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('notification_preferences');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });

        Schema::table('project_user', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });

        Schema::table('bugs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_id');
            $table->dropColumn('sla_due_at');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });

        Schema::dropIfExists('task_tag');
        Schema::dropIfExists('task_tags');
        Schema::dropIfExists('chat_message_reactions');

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_id');
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropColumn('mentions');
        });
    }
};
