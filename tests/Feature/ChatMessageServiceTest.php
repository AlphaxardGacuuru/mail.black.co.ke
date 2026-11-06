<?php

namespace Tests\Feature;

use App\Http\Services\ChatMessageService;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChatMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeConversation(User $a, User $b): ChatConversation
    {
        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$a->id, $b->id]);

        return $conversation;
    }

    public function test_send_creates_a_message_and_bumps_the_conversation_last_message_at(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->makeConversation($me, $other);

        $this->actingAs($me, 'sanctum');
        [$saved, $message, $chatMessage] = (new ChatMessageService)->send($conversation->id, 'Hello there');

        $this->assertTrue($saved);
        $this->assertSame('Message Sent', $message);
        $this->assertDatabaseHas('chat_messages', [
            'id' => $chatMessage->id,
            'conversation_id' => $conversation->id,
            'sender_id' => $me->id,
            'body' => 'Hello there',
        ]);
        $this->assertNotNull($conversation->refresh()->last_message_at);
    }

    public function test_send_rejects_an_empty_message_with_no_attachments(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->makeConversation($me, $other);

        $this->actingAs($me, 'sanctum');

        $this->expectException(ValidationException::class);

        (new ChatMessageService)->send($conversation->id, null);
    }

    public function test_send_fails_for_a_conversation_the_user_does_not_belong_to(): void
    {
        $me = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = $this->makeConversation($a, $b);

        $this->actingAs($me, 'sanctum');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        (new ChatMessageService)->send($conversation->id, 'Hi');
    }

    public function test_destroy_deletes_only_the_senders_own_message(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->makeConversation($me, $other);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $me->id,
            'body' => 'Delete me',
        ]);

        $this->actingAs($me, 'sanctum');
        [$deleted] = (new ChatMessageService)->destroy($message->id);

        $this->assertTrue($deleted);
        $this->assertSoftDeleted('chat_messages', ['id' => $message->id]);
    }

    public function test_destroy_rejects_a_message_belonging_to_another_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = $this->makeConversation($owner, $intruder);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $owner->id,
            'body' => 'Not yours',
        ]);

        $this->actingAs($intruder, 'sanctum');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        (new ChatMessageService)->destroy($message->id);
    }

    public function test_send_records_the_reply_to_message(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->makeConversation($me, $other);

        $original = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $other->id,
            'body' => 'Original message',
        ]);

        $this->actingAs($me, 'sanctum');
        [, , $reply] = (new ChatMessageService)->send($conversation->id, 'Replying', [], $original->id);

        $this->assertSame($original->id, $reply->reply_to_id);
    }

    public function test_toggle_star_stars_then_unstars_a_message(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->makeConversation($me, $other);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $other->id,
            'body' => 'Star me',
        ]);

        $this->actingAs($me, 'sanctum');

        [, , $isStarred] = (new ChatMessageService)->toggleStar($message->id);
        $this->assertTrue($isStarred);
        $this->assertDatabaseHas('chat_message_stars', ['message_id' => $message->id, 'user_id' => $me->id]);

        [, , $isStarred] = (new ChatMessageService)->toggleStar($message->id);
        $this->assertFalse($isStarred);
        $this->assertDatabaseMissing('chat_message_stars', ['message_id' => $message->id, 'user_id' => $me->id]);
    }

    public function test_forward_copies_the_message_into_another_conversation(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $thirdParty = User::factory()->create();

        $sourceConversation = $this->makeConversation($me, $other);
        $targetConversation = $this->makeConversation($me, $thirdParty);

        $message = ChatMessage::create([
            'conversation_id' => $sourceConversation->id,
            'sender_id' => $other->id,
            'body' => 'Forward this',
        ]);

        $this->actingAs($me, 'sanctum');
        [$saved, , $forwarded] = (new ChatMessageService)->forward($message->id, $targetConversation->id);

        $this->assertTrue($saved);
        $this->assertDatabaseHas('chat_messages', [
            'id' => $forwarded->id,
            'conversation_id' => $targetConversation->id,
            'sender_id' => $me->id,
            'body' => 'Forward this',
        ]);
        $this->assertNotNull($targetConversation->refresh()->last_message_at);
    }

    public function test_forward_fails_for_a_target_conversation_the_user_does_not_belong_to(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $sourceConversation = $this->makeConversation($me, $other);
        $unrelatedConversation = $this->makeConversation($a, $b);

        $message = ChatMessage::create([
            'conversation_id' => $sourceConversation->id,
            'sender_id' => $other->id,
            'body' => 'Nope',
        ]);

        $this->actingAs($me, 'sanctum');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        (new ChatMessageService)->forward($message->id, $unrelatedConversation->id);
    }
}
