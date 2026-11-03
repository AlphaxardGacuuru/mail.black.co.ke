<?php

namespace Tests\Feature;

use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use App\Notifications\JobApplicationsReadyNotification;
use App\Notifications\JobSearchPausedNotification;
use App\Notifications\NewMailReceivedEmailNotification;
use App\Notifications\NewMailReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function mailbox(User $user, string $address): MailgunAccount
    {
        $domain = MailgunDomain::firstOrCreate(
            ['user_id' => $user->id, 'domain' => 'example.com'],
            ['api_key' => 'key'],
        );

        return MailgunAccount::create([
            'user_id' => $user->id,
            'mailgun_domain_id' => $domain->id,
            'mailbox_address' => $address,
        ]);
    }

    public function test_every_category_starts_with_its_defaults(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notification-preferences')
            ->assertOk()
            ->assertJsonPath('email', $user->email);

        $categories = collect($response->json('categories'))->keyBy('key');

        $this->assertSame(array_keys(config('notifications.categories')), $categories->keys()->all());
        $this->assertSame(['inApp' => true, 'push' => true, 'email' => false], $categories['new_mail']['channels']);
        $this->assertSame(['inApp' => false, 'push' => false, 'email' => true], $categories['job_application_replied']['channels']);
    }

    public function test_one_switch_is_saved_without_touching_the_others(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/notification-preferences', ['preferences' => ['job_applications_ready' => ['email' => true]]])
            ->assertOk()
            ->assertJsonPath('message', 'Notification Settings Saved');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/notification-preferences', ['preferences' => ['job_applications_ready' => ['inApp' => false]]])
            ->assertOk();

        $this->assertSame(
            ['in_app' => false, 'push' => true, 'email' => true],
            $user->fresh()->notificationPreferences()['job_applications_ready'],
        );
    }

    public function test_unknown_categories_and_channels_are_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/notification-preferences', ['preferences' => ['made_up' => ['email' => true]]])
            ->assertUnprocessable();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/notification-preferences', ['preferences' => ['new_mail' => ['sms' => true]]])
            ->assertUnprocessable();

        $this->assertNull($user->fresh()->notification_preferences);
    }

    public function test_notifications_go_only_to_the_chosen_channels(): void
    {
        $user = User::factory()->create();

        $this->assertSame(['database', 'broadcast', WebPushChannel::class], (new JobApplicationsReadyNotification(2))->via($user));

        $user->notification_preferences = [
            'job_applications_ready' => ['push' => false, 'email' => true],
            'job_search_paused' => ['in_app' => false, 'push' => false, 'email' => false],
        ];
        $user->save();

        $this->assertSame(['database', 'broadcast', 'mail'], (new JobApplicationsReadyNotification(2))->via($user));
        $this->assertSame([], (new JobSearchPausedNotification('Too many bounces'))->via($user));
    }

    public function test_new_mail_email_is_separate_from_the_instant_alert(): void
    {
        $user = User::factory()->create();
        $message = new MailMessage(['subject' => 'Hello']);

        $this->assertSame(['database', 'broadcast', WebPushChannel::class], (new NewMailReceivedNotification($message))->via($user));
        $this->assertSame([], (new NewMailReceivedEmailNotification($message))->via($user));

        $user->notification_preferences = ['new_mail' => ['email' => true]];
        $user->save();

        $this->assertSame(['database', 'broadcast', WebPushChannel::class], (new NewMailReceivedNotification($message))->via($user));
        $this->assertSame(['mail'], (new NewMailReceivedEmailNotification($message))->via($user));
    }

    public function test_new_mail_is_never_emailed_to_a_mailbox_hosted_here(): void
    {
        $user = User::factory()->create(['email' => 'Me@Example.com']);
        $this->mailbox($user, 'me@example.com');
        $user->notification_preferences = ['new_mail' => ['email' => true]];
        $user->save();

        $this->assertSame([], (new NewMailReceivedEmailNotification(new MailMessage))->via($user));

        $newMail = collect($this->actingAs($user, 'sanctum')->getJson('/api/notification-preferences')->json('categories'))
            ->firstWhere('key', 'new_mail');

        $this->assertFalse($newMail['channels']['email']);
        $this->assertStringContainsString('would loop', $newMail['blocked']['email']);
    }
}
