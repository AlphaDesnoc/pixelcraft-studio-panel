<?php

use App\Models\ProjectTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (ProjectTemplate::query()->exists()) {
            return;
        }

        ProjectTemplate::query()->create([
            'name' => 'Modding — standard',
            'description' => 'Ranks dev/QA/art, listes Kanban et tags par défaut.',
            'payload' => [
                'ranks' => [
                    ['name' => 'Développement', 'slug' => 'dev', 'color' => '#7c5cff', 'manages_bugs' => false],
                    ['name' => 'QA', 'slug' => 'qa', 'color' => '#22c55e', 'manages_bugs' => true],
                    ['name' => 'Art', 'slug' => 'art', 'color' => '#f59e0b', 'manages_bugs' => false],
                ],
                'tags' => [
                    ['name' => 'Feature', 'color' => '#7c5cff'],
                    ['name' => 'Fix', 'color' => '#ef4444'],
                    ['name' => 'Polish', 'color' => '#06b6d4'],
                ],
            ],
        ]);

        ProjectTemplate::query()->create([
            'name' => 'Contenu — minimal',
            'description' => 'Un rank contenu avec tags basiques.',
            'payload' => [
                'ranks' => [
                    ['name' => 'Contenu', 'slug' => 'content', 'color' => '#a855f7', 'manages_bugs' => true],
                ],
                'tags' => [
                    ['name' => 'Quest', 'color' => '#8b5cf6'],
                    ['name' => 'NPC', 'color' => '#14b8a6'],
                ],
            ],
        ]);
    }

    public function down(): void
    {
        ProjectTemplate::query()->whereIn('name', ['Modding — standard', 'Contenu — minimal'])->delete();
    }
};
