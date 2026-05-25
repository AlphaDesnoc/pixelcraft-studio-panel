<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('list_id')->nullable()->after('project_id')->constrained('task_lists')->nullOnDelete();
            $table->unsignedInteger('position')->default(0)->after('priority');
            $table->date('start_date')->nullable()->after('due_date');
            $table->unsignedTinyInteger('progress')->default(0)->after('start_date');

            $table->index(['list_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['list_id', 'position']);
            $table->dropConstrainedForeignId('list_id');
            $table->dropColumn(['position', 'start_date', 'progress']);
        });
    }
};
