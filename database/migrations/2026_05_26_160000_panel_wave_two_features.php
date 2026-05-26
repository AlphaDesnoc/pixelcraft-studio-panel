<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme_preference', 16)->default('dark')->after('two_factor_confirmed_at');
            $table->json('dashboard_widgets')->nullable()->after('theme_preference');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('recurrence_rule', 32)->nullable()->after('archived_at');
            $table->foreignId('recurrence_source_id')->nullable()->after('recurrence_rule')
                ->constrained('tasks')->nullOnDelete();
            $table->timestamp('next_recurrence_at')->nullable()->after('recurrence_source_id');
            $table->unsignedInteger('estimated_minutes')->nullable()->after('next_recurrence_at');
            $table->unsignedInteger('logged_minutes')->default(0)->after('estimated_minutes');
            $table->timestamp('auto_archive_at')->nullable()->after('logged_minutes');
        });

        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('rank_id')->nullable()->constrained('ranks')->nullOnDelete();
            $table->string('name', 80);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('priority', 16)->default('medium');
            $table->json('checklist')->nullable();
            $table->timestamps();
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->primary(['task_id', 'depends_on_task_id']);
        });

        Schema::create('direct_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direct_message_id')->constrained('direct_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 32);
            $table->timestamps();
            $table->unique(['direct_message_id', 'user_id', 'emoji']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->timestamp('pinned_at')->nullable()->after('edited_at');
            $table->foreignId('pinned_by')->nullable()->after('pinned_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('project_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->json('payload');
            $table->timestamps();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('bug_id')->nullable()->after('project_id')
                ->constrained('bugs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bug_id');
        });

        Schema::dropIfExists('project_templates');
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pinned_by');
            $table->dropColumn('pinned_at');
        });
        Schema::dropIfExists('direct_message_reactions');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_templates');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurrence_source_id');
            $table->dropColumn([
                'recurrence_rule',
                'next_recurrence_at',
                'estimated_minutes',
                'logged_minutes',
                'auto_archive_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['theme_preference', 'dashboard_widgets']);
        });
    }
};
