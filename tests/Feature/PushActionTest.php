<?php

namespace Tests\Feature;

use App\Events\ChatConversationReadEvent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use App\Notifications\AdminNewUserSignupNotification;
use App\Notifications\NewChatMessageNotification;
use App\Notifications\NewMailReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PushActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    /**
     * The web push payload a notification would send $notifiable.
     *
     * @return array<string, mixed>
     */
    private function push(object $notification, User $notifiable): array
    {
        return $notification
            ->toWebPush($notifiable, $notification)
            ->toArray();
    }

    private function unreadMail(User $user): MailMessage
    {
        $thread = MailThread::create([
            'user_id' => $user->id,
            'subject' => 'Interview',
            'has_unread' => true,
        ]);

        return MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $user->id,
            'direction' => 'inbound',
            'folder' => 'inbox',
            'from_address' => ['address' => 'hr@acme.co.ke', 'name' => 'Acme HR'],
            'to' => [['address' => 'me@example.com']],
            'subject' => 'Interview',
            'status' => 'received',
            'is_read' => false,
        ]);
    }

    private function chatMessage(User $sender, User $recipient): ChatMessage
    {
        $conversation = ChatConversation::create(['type' => 'direct']);

        $conversation
            ->participants()
            ->attach([$sender->id, $recipient->id]);

        return ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => 'Are you free tomorrow?',
        ]);
    }

    public function test_mark_as_read_reads_the_mail_thread_through_the_signed_link(): void
    {
        $user = User::factory()->create();
        $mail = $this->unreadMail($user);

        $push = $this->push(new NewMailReceivedNotification($mail), $user);

        $this->assertSame(['mark-read', 'archive'], array_column($push['actions'], 'action'));
        $this->assertSame("/mail/{$mail->mail_thread_id}/show", $push['data']['url']);

        $this->postJson($push['data']['actions']['mark-read']['post'])
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertTrue($mail->fresh()->is_read);
        $this->assertFalse($mail->thread->fresh()->has_unread);
    }

    public function test_archive_moves_the_mail_thread_to_the_archive(): void
    {
        $user = User::factory()->create();
        $mail = $this->unreadMail($user);

        $push = $this->push(new NewMailReceivedNotification($mail), $user);

        $this->postJson($push['data']['actions']['archive']['post'])
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertSame('archive', $mail->fresh()->folder);
    }

    public function test_mark_as_read_reads_the_chat_and_sends_the_read_receipt(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $message = $this->chatMessage($sender, $recipient);

        $push = $this->push(new NewChatMessageNotification($message), $recipient);

        $this->assertSame(['reply', 'mark-read'], array_column($push['actions'], 'action'));
        $this->assertSame("/chats/{$message->conversation_id}/show", $push['data']['actions']['reply']['url']);

        $this->postJson($push['data']['actions']['mark-read']['post'])
            ->assertOk();

        $this->assertNotNull(
            $message->conversation
                ->participants()
                ->where('users.id', $recipient->id)
                ->first()
                ->pivot
                ->last_read_at
        );
        Event::assertDispatched(
            ChatConversationReadEvent::class,
            fn (ChatConversationReadEvent $event) => $event->readByUserId === $recipient->id
        );
    }

    public function test_the_admin_can_verify_a_signup_from_a_push(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $newUser = User::factory()->create(['verified' => false]);

        $push = $this->push(new AdminNewUserSignupNotification($newUser), $admin);

        $this->assertSame(['verify', 'users'], array_column($push['actions'], 'action'));
        $this->assertSame('/admin/users', $push['data']['actions']['users']['url']);

        $this->postJson($push['data']['actions']['verify']['post'])
            ->assertOk();

        $this->assertTrue($newUser->fresh()->verified);
    }

    public function test_admin_actions_are_refused_for_anyone_else(): void
    {
        $customer = User::factory()->create();
        $newUser = User::factory()->create(['verified' => false]);

        $push = $this->push(new AdminNewUserSignupNotification($newUser), $customer);

        $this->postJson($push['data']['actions']['verify']['post'])
            ->assertForbidden();

        $this->assertFalse($newUser->fresh()->verified);
    }

    public function test_a_push_action_needs_a_valid_unexpired_signature(): void
    {
        $user = User::factory()->create();
        $mail = $this->unreadMail($user);

        $this->postJson("/api/push-actions/{$user->id}/threads/{$mail->mail_thread_id}/read")
            ->assertForbidden();

        $expired = URL::temporarySignedRoute(
            'push-actions.threads.read',
            now()->subMinute(),
            ['user' => $user->id, 'thread' => $mail->mail_thread_id],
            absolute: false
        );

        $this->postJson($expired)
            ->assertForbidden();

        $push = $this->push(new NewMailReceivedNotification($mail), $user);

        $this->travel(8)
            ->days();

        $this->postJson($push['data']['actions']['mark-read']['post'])
            ->assertForbidden();

        $this->assertFalse($mail->fresh()->is_read);
    }

    public function test_a_signed_link_only_works_for_the_user_it_was_sent_to(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $mail = $this->unreadMail($owner);
        $chat = $this->chatMessage($owner, User::factory()->create());

        collect([
            ['push-actions.threads.read', ['thread' => $mail->mail_thread_id]],
            ['push-actions.threads.archive', ['thread' => $mail->mail_thread_id]],
            ['push-actions.chat.conversations.read', ['conversation' => $chat->conversation_id]],
        ])
            ->each(fn (array $action) => $this
                ->postJson(URL::temporarySignedRoute(
                    $action[0],
                    now()->addDay(),
                    ['user' => $intruder->id, ...$action[1]],
                    absolute: false
                ))
                ->assertForbidden());

        $this->assertFalse($mail->fresh()->is_read);
        $this->assertSame('inbox', $mail->fresh()->folder);
        Event::assertNotDispatched(ChatConversationReadEvent::class);
    }

    public function test_every_web_push_notification_has_a_button(): void
    {
        collect(glob(app_path('Notifications/*.php')))
            ->map(fn (string $path) => file_get_contents($path))
            ->filter(fn (string $source) => str_contains($source, 'function toWebPush'))
            ->each(fn (string $source) => $this->assertMatchesRegularExpression('/->(openAction|signedAction)\(/', $source));
    }
}
