<?php

namespace Tests\Feature;

use App\Http\Services\ChatConversationService;
use App\Http\Services\ChatMessageService;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatConversationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_conversation_creates_one_with_both_participants(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($me, 'sanctum');
        $service = new ChatConversationService;

        [$saved, $message, $conversation] = $service->startWith($other->id);

        $this->assertTrue($saved);
        $this->assertSame('Conversation Started', $message);
        $this->assertDatabaseHas('chat_conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $me->id,
        ]);
        $this->assertDatabaseHas('chat_conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $other->id,
        ]);
    }

    public function test_starting_a_conversation_twice_reuses_the_existing_one(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($me, 'sanctum');
        $service = new ChatConversationService;

        [, , $first] = $service->startWith($other->id);
        [, $message, $second] = $service->startWith($other->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Conversation Already Exists', $message);
        $this->assertSame(1, ChatConversation::count());
    }

    public function test_marking_a_conversation_read_updates_the_participants_pivot(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($me, 'sanctum');
        $service = new ChatConversationService;

        [$status] = $service->markRead($conversation->id);

        $this->assertTrue($status);
        $this->assertNotNull(
            $conversation->participants()->where('users.id', $me->id)->first()->pivot->last_read_at
        );
    }

    public function test_a_message_reads_as_read_once_the_recipient_has_caught_up(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($me, 'sanctum');
        [, , $message] = (new ChatMessageService)->send($conversation->id, 'Hello there');

        $this->actingAs($other, 'sanctum');
        [, , , $beforeRead] = (new ChatConversationService)->show($conversation->id);
        $this->assertFalse($beforeRead->getCollection()->firstWhere('id', $message->id)->is_read_by_recipient);

        (new ChatConversationService)->markRead($conversation->id);

        $this->actingAs($me, 'sanctum');
        [, , , $afterRead] = (new ChatConversationService)->show($conversation->id);
        $this->assertTrue($afterRead->getCollection()->firstWhere('id', $message->id)->is_read_by_recipient);
    }

    public function test_archiving_a_conversation_hides_it_from_the_index_for_that_user_only(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($me, 'sanctum');
        [$status, $message, $isArchived] = (new ChatConversationService)->toggleArchive($conversation->id);

        $this->assertTrue($status);
        $this->assertSame('Conversation Archived', $message);
        $this->assertTrue($isArchived);

        [, , $myConversations] = (new ChatConversationService)->index();
        $this->assertCount(0, $myConversations);

        $this->actingAs($other, 'sanctum');
        [, , $otherConversations] = (new ChatConversationService)->index();
        $this->assertCount(1, $otherConversations);
    }

    public function test_toggling_archive_twice_unarchives_the_conversation(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($me, 'sanctum');
        $service = new ChatConversationService;

        $service->toggleArchive($conversation->id);
        [, $message, $isArchived] = $service->toggleArchive($conversation->id);

        $this->assertSame('Conversation Unarchived', $message);
        $this->assertFalse($isArchived);

        [, , $myConversations] = $service->index();
        $this->assertCount(1, $myConversations);
    }

    public function test_hiding_a_conversation_removes_it_from_the_index_for_that_user_only(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($me, 'sanctum');
        [$status, $message] = (new ChatConversationService)->hideForMe($conversation->id);

        $this->assertTrue($status);
        $this->assertSame('Conversation Removed', $message);

        [, , $myConversations] = (new ChatConversationService)->index();
        $this->assertCount(0, $myConversations);

        $this->actingAs($other, 'sanctum');
        [, , $otherConversations] = (new ChatConversationService)->index();
        $this->assertCount(1, $otherConversations);
    }

    public function test_starting_a_conversation_again_after_hiding_it_reuses_the_same_one_but_stays_hidden(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($me, 'sanctum');
        $service = new ChatConversationService;

        [, , $first] = $service->startWith($other->id);
        $service->hideForMe($first->id);

        // Re-initiating alone (no new message yet) must not resurrect the
        // conversation or its history — only a message landing after the
        // cutoff does that.
        [, $message, $second] = $service->startWith($other->id);

        $this->assertSame('Conversation Already Exists', $message);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ChatConversation::count());

        [, , $myConversations] = $service->index();
        $this->assertCount(0, $myConversations);
    }

    public function test_hiding_a_conversation_hides_its_message_history_for_that_user_only(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($other, 'sanctum');
        (new ChatMessageService)->send($conversation->id, 'Old message 1');
        (new ChatMessageService)->send($conversation->id, 'Old message 2');

        $this->actingAs($me, 'sanctum');
        (new ChatConversationService)->hideForMe($conversation->id);

        [, , , $myMessages] = (new ChatConversationService)->show($conversation->id);
        $this->assertCount(0, $myMessages->getCollection());

        $this->actingAs($other, 'sanctum');
        [, , , $otherMessages] = (new ChatConversationService)->show($conversation->id);
        $this->assertCount(2, $otherMessages->getCollection());
    }

    public function test_a_reply_after_hiding_reappears_with_only_the_new_message_for_the_deleter(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $conversation = ChatConversation::create(['type' => 'direct']);
        $conversation->participants()->attach([$me->id, $other->id]);

        $this->actingAs($other, 'sanctum');
        (new ChatMessageService)->send($conversation->id, 'Old message');

        $this->actingAs($me, 'sanctum');
        $service = new ChatConversationService;
        $service->hideForMe($conversation->id);

        [, , $myConversationsBeforeReply] = $service->index();
        $this->assertCount(0, $myConversationsBeforeReply);

        $this->actingAs($other, 'sanctum');
        [, , $newMessage] = (new ChatMessageService)->send($conversation->id, 'New message after delete');

        $this->actingAs($me, 'sanctum');
        [, , $myConversationsAfterReply] = $service->index();
        $this->assertCount(1, $myConversationsAfterReply);

        [, , , $myMessages] = $service->show($conversation->id);
        $this->assertCount(1, $myMessages->getCollection());
        $this->assertSame($newMessage->id, $myMessages->getCollection()->first()->id);

        $this->actingAs($other, 'sanctum');
        [, , , $otherMessages] = (new ChatConversationService)->show($conversation->id);
        $this->assertCount(2, $otherMessages->getCollection());
    }

    public function test_starting_a_conversation_again_after_archiving_it_unarchives_it(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($me, 'sanctum');
        $service = new ChatConversationService;

        [, , $first] = $service->startWith($other->id);
        $service->toggleArchive($first->id);

        $service->startWith($other->id);

        [, , $myConversations] = $service->index();
        $this->assertCount(1, $myConversations);
        $this->assertSame(1, ChatConversation::count());
    }

    public function test_pair_key_is_unique_at_the_database_level(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $pairKey = collect([$me->id, $other->id])->sort()->implode(':');
        ChatConversation::create(['type' => 'direct', 'pair_key' => $pairKey]);

        // This is what actually guarantees at most one direct conversation
        // per pair under concurrent requests — startWith()'s find-then-create
        // has a race window, but the database won't let a second row with
        // the same pair_key land regardless.
        $this->expectException(\Illuminate\Database\QueryException::class);

        ChatConversation::create(['type' => 'direct', 'pair_key' => $pairKey]);
    }
}
