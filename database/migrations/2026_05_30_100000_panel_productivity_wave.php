<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'capacity_threshold')) {
                $table->unsignedSmallInteger('capacity_threshold')->nullable()->after('status');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'snoozed_until')) {
                $table->timestamp('snoozed_until')->nullable()->after('archived_at');
            }
        });

        if (! Schema::hasTable('task_time_entries')) {
            Schema::create('task_time_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamp('started_at');
                $table->timestamp('stopped_at')->nullable();
                $table->unsignedInteger('minutes')->nullable();
                $table->timestamps();
                $table->index(['task_id', 'user_id', 'stopped_at']);
            });
        }

        if (! Schema::hasTable('task_reminders')) {
            Schema::create('task_reminders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamp('remind_at');
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['remind_at', 'sent_at']);
            });
        }

        if (! Schema::hasTable('kanban_saved_views')) {
            Schema::create('kanban_saved_views', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->json('filters');
                $table->boolean('is_shared')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('project_automation_rules')) {
            Schema::create('project_automation_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('trigger', 64);
                $table->json('trigger_config')->nullable();
                $table->string('action', 64);
                $table->json('action_config')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('milestones')) {
            Schema::create('milestones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->date('start_date')->nullable();
                $table->date('due_date')->nullable();
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('milestone_task')) {
            Schema::create('milestone_task', function (Blueprint $table) {
                $table->foreignId('milestone_id')->constrained()->cascadeOnDelete();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->primary(['milestone_id', 'task_id']);
            });
        }

        if (! Schema::hasTable('task_view_presences')) {
            Schema::create('task_view_presences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('context', 32);
                $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamp('last_seen_at');
                $table->unique(['project_id', 'user_id', 'context', 'task_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_view_presences');
        Schema::dropIfExists('milestone_task');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('project_automation_rules');
        Schema::dropIfExists('kanban_saved_views');
        Schema::dropIfExists('task_reminders');
        Schema::dropIfExists('task_time_entries');

        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'snoozed_until')) {
                $table->dropColumn('snoozed_until');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'capacity_threshold')) {
                $table->dropColumn('capacity_threshold');
            }
        });
    }
};
