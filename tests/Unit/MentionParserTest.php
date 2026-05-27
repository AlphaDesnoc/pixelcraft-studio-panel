<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Rank;
use App\Models\User;
use App\Support\MentionParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentionParserTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(User $owner): Project
    {
        return Project::query()->create([
            'name' => 'Test Project',
            'slug' => 'test-project',
            'owner_id' => $owner->id,
            'status' => Project::STATUS_ACTIVE,
        ]);
    }

    public function test_extracts_rank_mentions_in_global_chat(): void
    {
        $owner = User::factory()->create();
        $project = $this->makeProject($owner);
        $rank = Rank::query()->create([
            'project_id' => $project->id,
            'name' => 'Config',
            'slug' => 'config',
            'color' => '#a855f7',
            'position' => 0,
        ]);

        $user = User::factory()->create(['email' => 'alice@example.com']);
        $ranks = collect([$rank]);
        $users = collect([$user]);

        $mentions = MentionParser::extract('Salut @config, besoin d\'aide', $users, $ranks);

        $this->assertCount(1, $mentions);
        $this->assertSame('rank', $mentions[0]['type']);
        $this->assertSame($rank->id, $mentions[0]['id']);
        $this->assertSame('config', $mentions[0]['slug']);
    }

    public function test_rank_slug_takes_priority_over_user_pseudo(): void
    {
        $owner = User::factory()->create();
        $project = $this->makeProject($owner);
        $rank = Rank::query()->create([
            'project_id' => $project->id,
            'name' => 'Config',
            'slug' => 'config',
            'color' => '#a855f7',
            'position' => 0,
        ]);

        $user = User::factory()->create(['email' => 'config@example.com']);
        $mentions = MentionParser::extract('@config', collect([$user]), collect([$rank]));

        $this->assertCount(1, $mentions);
        $this->assertSame('rank', $mentions[0]['type']);
    }

    public function test_notified_user_ids_includes_rank_members(): void
    {
        $owner = User::factory()->create();
        $project = $this->makeProject($owner);
        $rank = Rank::query()->create([
            'project_id' => $project->id,
            'name' => 'Config',
            'slug' => 'config',
            'color' => '#a855f7',
            'position' => 0,
        ]);

        $member = User::factory()->create();
        $rank->members()->attach($member->id);

        $ids = MentionParser::notifiedUserIds($project, [[
            'type' => 'rank',
            'id' => $rank->id,
            'slug' => 'config',
            'name' => 'Config',
        ]]);

        $this->assertTrue($ids->contains($member->id));
    }
}
