<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['task_lists', 'notes', 'calendar_events', 'file_nodes', 'sheets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('rank_id')
                    ->nullable()
                    ->after('project_id')
                    ->constrained('ranks')
                    ->nullOnDelete();
                $table->index(['project_id', 'rank_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['task_lists', 'notes', 'calendar_events', 'file_nodes', 'sheets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('rank_id');
            });
        }
    }
};
