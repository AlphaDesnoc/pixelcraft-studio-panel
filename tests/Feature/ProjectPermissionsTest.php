<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Support\ProjectAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_update_member_permissions(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $member = User::factory()->create();

        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $project->members()->attach($owner->id, ['role' => ProjectAccess::ROLE_OWNER]);
        $project->members()->attach($manager->id, ['role' => ProjectAccess::ROLE_MANAGER]);
        $project->members()->attach($member->id, ['role' => ProjectAccess::ROLE_MEMBER]);

        $response = $this->actingAs($manager)->putJson(
            route('projects.members.permissions', [$project->slug, $member->id]),
            [
                'permissions' => [
                    'kanban' => true,
                    'kanban_write' => false,
                    'calendar' => true,
                    'calendar_write' => true,
                ],
            ],
        );

        $response->assertOk();
        $this->assertFalse(
            $project->members()->whereKey($member->id)->first()->pivot->permissions['kanban_write'],
        );
    }

    public function test_regular_member_cannot_update_permissions(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();

        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $project->members()->attach($owner->id, ['role' => ProjectAccess::ROLE_OWNER]);
        $project->members()->attach($member->id, ['role' => ProjectAccess::ROLE_MEMBER]);
        $project->members()->attach($other->id, ['role' => ProjectAccess::ROLE_MEMBER]);

        $this->actingAs($member)->putJson(
            route('projects.members.permissions', [$project->slug, $other->id]),
            ['permissions' => ['kanban' => false]],
        )->assertForbidden();
    }
}
