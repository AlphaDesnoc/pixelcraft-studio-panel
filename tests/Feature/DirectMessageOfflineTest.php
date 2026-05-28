<?php

namespace Tests\Feature;

use App\Models\DirectConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectMessageOfflineTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_conversations_without_reverb(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        DirectConversation::query()->create([
            'user_one_id' => min($alice->id, $bob->id),
            'user_two_id' => max($alice->id, $bob->id),
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($alice)->getJson(route('messages.conversations.index'));

        $response->assertOk();
        $response->assertJsonStructure(['conversations', 'contacts']);
    }
}
