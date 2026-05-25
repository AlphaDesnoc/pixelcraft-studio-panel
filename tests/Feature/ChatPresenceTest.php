<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Support\SpaceChatAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatPresenceTest extends TestCase
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

    public function test_global_chat_lists_project_members(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $project = $this->makeProject($admin);

        $project->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        $members = SpaceChatAccess::membersWithPresence($project, 'global', $admin);

        $this->assertNotEmpty($members);
        $this->assertTrue(collect($members)->contains(fn ($m) => $m['id'] === $member->id));
        $this->assertTrue(collect($members)->firstWhere('id', $admin->id)['is_online']);
    }

    public function test_presence_endpoint_returns_members(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $project = $this->makeProject($admin);

        $response = $this->actingAs($admin)->getJson(
            route('projects.chat.presence', $project).'?space=global'
        );

        $response->assertOk();
        $response->assertJsonStructure(['members', 'online_count']);
        $this->assertNotEmpty($response->json('members'));
    }
}
