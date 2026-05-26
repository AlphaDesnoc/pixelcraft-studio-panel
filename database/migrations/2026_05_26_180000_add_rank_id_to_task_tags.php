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
        if (! Schema::hasColumn('task_tags', 'rank_id')) {
            Schema::table('task_tags', function (Blueprint $table) {
                $table->foreignId('rank_id')->nullable()->after('project_id')->constrained('ranks')->cascadeOnDelete();
            });
        }

        if ($this->indexExists('task_tags', 'task_tags_project_id_name_unique')) {
            $this->ensureProjectIdIndex();

            Schema::table('task_tags', function (Blueprint $table) {
                $table->dropUnique(['project_id', 'name']);
            });
        }

        $this->backfillRankIds();
        $this->dedupeTags();

        if (! $this->indexExists('task_tags', 'task_tags_project_id_rank_id_name_unique')) {
            Schema::table('task_tags', function (Blueprint $table) {
                $table->unique(['project_id', 'rank_id', 'name']);
            });
        }

        if ($this->indexExists('task_tags', 'task_tags_project_id_index')) {
            Schema::table('task_tags', function (Blueprint $table) {
                $table->dropIndex('task_tags_project_id_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('task_tags', 'task_tags_project_id_rank_id_name_unique')) {
            Schema::table('task_tags', function (Blueprint $table) {
                $table->dropUnique(['project_id', 'rank_id', 'name']);
            });
        }

        if (Schema::hasColumn('task_tags', 'rank_id')) {
            Schema::table('task_tags', function (Blueprint $table) {
                $table->dropConstrainedForeignId('rank_id');
            });
        }

        if (! $this->indexExists('task_tags', 'task_tags_project_id_name_unique')) {
            Schema::table('task_tags', function (Blueprint $table) {
                $table->unique(['project_id', 'name']);
            });
        }
    }

    private function backfillRankIds(): void
    {
        TaskTag::query()
            ->whereNull('rank_id')
            ->orderBy('id')
            ->each(function (TaskTag $tag) {
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
                    $remaining = $rankIds->values();
                    $primaryRankId = $remaining->shift();
                    $tag->update(['rank_id' => $primaryRankId]);

                    foreach ($remaining as $rankId) {
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

    private function dedupeTags(): void
    {
        $groups = DB::table('task_tags')
            ->select('project_id', 'rank_id', 'name', DB::raw('COUNT(*) as total'), DB::raw('MIN(id) as keep_id'))
            ->groupBy('project_id', 'rank_id', 'name')
            ->having('total', '>', 1)
            ->get();

        foreach ($groups as $group) {
            $duplicateIds = TaskTag::query()
                ->where('project_id', $group->project_id)
                ->where(function ($query) use ($group) {
                    if ($group->rank_id === null) {
                        $query->whereNull('rank_id');
                    } else {
                        $query->where('rank_id', $group->rank_id);
                    }
                })
                ->where('name', $group->name)
                ->where('id', '!=', $group->keep_id)
                ->pluck('id');

            foreach ($duplicateIds as $duplicateId) {
                $taskIds = DB::table('task_tag')
                    ->where('task_tag_id', $duplicateId)
                    ->pluck('task_id');

                foreach ($taskIds as $taskId) {
                    $alreadyLinked = DB::table('task_tag')
                        ->where('task_id', $taskId)
                        ->where('task_tag_id', $group->keep_id)
                        ->exists();

                    if ($alreadyLinked) {
                        DB::table('task_tag')
                            ->where('task_id', $taskId)
                            ->where('task_tag_id', $duplicateId)
                            ->delete();
                    } else {
                        DB::table('task_tag')
                            ->where('task_id', $taskId)
                            ->where('task_tag_id', $duplicateId)
                            ->update(['task_tag_id' => $group->keep_id]);
                    }
                }

                TaskTag::query()->where('id', $duplicateId)->delete();
            }
        }
    }

    private function ensureProjectIdIndex(): void
    {
        if ($this->indexExists('task_tags', 'task_tags_project_id_index')) {
            return;
        }

        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $indexes = $connection->select(
            'SELECT index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS columns
             FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name != ?
             GROUP BY index_name',
            [$database, 'task_tags', 'PRIMARY']
        );

        foreach ($indexes as $index) {
            if ($index->columns === 'project_id') {
                return;
            }
        }

        Schema::table('task_tags', function (Blueprint $table) {
            $table->index('project_id', 'task_tags_project_id_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$database, $table, $indexName]
        );

        return count($result) > 0;
    }
};
