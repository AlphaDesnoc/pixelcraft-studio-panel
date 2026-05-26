<?php

use App\Models\TaskTag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_tags', function (Blueprint $table) {
            $table->foreignId('rank_id')->nullable()->after('project_id')->constrained('ranks')->cascadeOnDelete();
        });

        Schema::table('task_tags', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'name']);
        });

        $this->backfillRankIds();

        Schema::table('task_tags', function (Blueprint $table) {
            $table->unique(['project_id', 'rank_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('task_tags', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'rank_id', 'name']);
            $table->dropConstrainedForeignId('rank_id');
            $table->unique(['project_id', 'name']);
        });
    }

    private function backfillRankIds(): void
    {
        TaskTag::query()->orderBy('id')->each(function (TaskTag $tag) {
            $rankIds = DB::table('task_tag')
                ->join('tasks', 'tasks.id', '=', 'task_tag.task_id')
                ->join('task_lists', 'task_lists.id', '=', 'tasks.list_id')
                ->where('task_tag.task_tag_id', $tag->id)
                ->pluck('task_lists.rank_id')
                ->unique()
                ->values();

            if ($rankIds->count() === 1) {
                $tag->update(['rank_id' => $rankIds->first()]);

                return;
            }

            if ($rankIds->count() > 1) {
                $primaryRankId = $rankIds->shift();
                $tag->update(['rank_id' => $primaryRankId]);

                foreach ($rankIds as $rankId) {
                    $duplicate = TaskTag::query()->firstOrCreate(
                        [
                            'project_id' => $tag->project_id,
                            'rank_id' => $rankId,
                            'name' => $tag->name,
                        ],
                        ['color' => $tag->color],
                    );

                    $taskIds = DB::table('task_tag')
                        ->join('tasks', 'tasks.id', '=', 'task_tag.task_id')
                        ->join('task_lists', 'task_lists.id', '=', 'tasks.list_id')
                        ->where('task_tag.task_tag_id', $tag->id)
                        ->where('task_lists.rank_id', $rankId)
                        ->pluck('tasks.id');

                    foreach ($taskIds as $taskId) {
                        DB::table('task_tag')
                            ->where('task_id', $taskId)
                            ->where('task_tag_id', $tag->id)
                            ->update(['task_tag_id' => $duplicate->id]);
                    }
                }

                return;
            }

            $fallbackRankId = DB::table('ranks')
                ->where('project_id', $tag->project_id)
                ->orderBy('position')
                ->value('id');

            $tag->update(['rank_id' => $fallbackRankId]);
        });
    }
};
