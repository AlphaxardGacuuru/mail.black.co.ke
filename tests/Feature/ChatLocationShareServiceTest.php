<?php

namespace Tests\Feature;

use App\Http\Services\ChatLocationShareService;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatLocationShareServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_share_turns_on_the_toggle_on_the_participant_pivot(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($me, 'sanctum');
        [$status, $message] = (new ChatLocationShareService)->start($conversation->id);

        $this->assertTrue($status);
        $this->assertSame('Location Sharing Started', $message);
        $this->assertDatabaseHas('chat_conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $me->id,
            'is_sharing_location' => true,
        ]);
        $this->assertDatabaseHas('chat_conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $other->id,
            'is_sharing_location' => false,
        ]);
    }

    public function test_starting_a_share_twice_is_rejected(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($me, 'sanctum');
        $service = new ChatLocationShareService;
        $service->start($conversation->id);

        [$status, $message] = $service->start($conversation->id);

        $this->assertFalse($status);
        $this->assertSame('You Are Already Sharing Your Location In This Conversation', $message);
    }

    public function test_both_participants_can_share_their_location_independently(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($me, 'sanctum');
        [$myStatus] = (new ChatLocationShareService)->start($conversation->id);

        $this->actingAs($other, 'sanctum');
        [$otherStatus] = (new ChatLocationShareService)->start($conversation->id);

        $this->assertTrue($myStatus);
        $this->assertTrue($otherStatus);
        $this->assertDatabaseHas('chat_conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $me->id,
            'is_sharing_location' => true,
        ]);
        $this->assertDatabaseHas('chat_conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $other->id,
            'is_sharing_location' => true,
        ]);
    }

    public function test_updating_position_only_affects_the_callers_own_pivot_row(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($me, 'sanctum');
        (new ChatLocationShareService)->start($conversation->id);

        [$status, $message] = (new ChatLocationShareService)->updatePosition($conversation->id, [
            'latitude' => -1.286389,
            'longitude' => 36.817223,
            'accuracyMeters' => 12.5,
        ]);

        $this->assertTrue($status);
        $this->assertSame('Location Updated', $message);

        $myPivot = $conversation->participants()->where('users.id', $me->id)->first()->pivot;
        $this->assertEqualsWithDelta(-1.286389, $myPivot->location_latitude, 0.00001);
        $this->assertEqualsWithDelta(36.817223, $myPivot->location_longitude, 0.00001);
        $this->assertNotNull($myPivot->location_updated_at);

        // The other participant hasn't turned sharing on — updating "their"
        // position must fail rather than silently touching their row.
        $this->actingAs($other, 'sanctum');
        [$otherStatus] = (new ChatLocationShareService)->updatePosition($conversation->id, [
            'latitude' => 0,
            'longitude' => 0,
        ]);
        $this->assertFalse($otherStatus);
    }

    public function test_stopping_a_share_turns_the_toggle_off(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($me, 'sanctum');
        $service = new ChatLocationShareService;
        $service->start($conversation->id);

        [$status, $message] = $service->stop($conversation->id);

        $this->assertTrue($status);
        $this->assertSame('Location Sharing Stopped', $message);
        $this->assertDatabaseHas('chat_conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $me->id,
            'is_sharing_location' => false,
        ]);

        // Nothing left to stop the second time.
        [$secondStatus, $secondMessage] = $service->stop($conversation->id);
        $this->assertFalse($secondStatus);
        $this->assertSame('No Active Location Share Found', $secondMessage);
    }

    public function test_starting_again_after_stopping_turns_the_toggle_back_on(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($me, 'sanctum');
        $service = new ChatLocationShareService;
        $service->start($conversation->id);
        $service->stop($conversation->id);

        [$status] = $service->start($conversation->id);

        $this->assertTrue($status);
        $this->assertDatabaseHas('chat_conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $me->id,
            'is_sharing_location' => true,
        ]);
    }

    public function test_the_full_http_flow_surfaces_the_share_to_the_other_participant(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($me, 'sanctum')
            ->postJson("/api/chat/conversations/{$conversation->id}/location")
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->actingAs($me, 'sanctum')
            ->patchJson("/api/chat/conversations/{$conversation->id}/location", [
                'latitude' => -1.286389,
                'longitude' => 36.817223,
            ])
            ->assertOk()
            ->assertJsonPath('status', true);

        // From the other participant's side: the share shows up as
        // "otherUserLocationShare", not "myLocationShare".
        $this->actingAs($other, 'sanctum')
            ->getJson("/api/chat/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.conversation.otherUserLocationShare.senderId', $me->id)
            ->assertJsonPath('data.conversation.otherUserLocationShare.latitude', -1.286389)
            ->assertJsonPath('data.conversation.myLocationShare', null);

        // From the sharer's own side, the fields are swapped.
        $this->actingAs($me, 'sanctum')
            ->getJson("/api/chat/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.conversation.myLocationShare.senderId', $me->id)
            ->assertJsonPath('data.conversation.otherUserLocationShare', null);

        $this->actingAs($me, 'sanctum')
            ->deleteJson("/api/chat/conversations/{$conversation->id}/location")
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/chat/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.conversation.otherUserLocationShare', null);
    }
}
