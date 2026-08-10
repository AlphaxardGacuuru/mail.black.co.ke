<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_includes_subscription_plans_in_the_spa_payload(): void
    {
        $starterPlan = $this->createSubscriptionPlan(
            name: 'Starter',
            monthly: 4900,
            yearly: 49000,
            features: ['Property tracking', 'Billing overview']
        );

        $growthPlan = $this->createSubscriptionPlan(
            name: 'Growth',
            monthly: 9900,
            yearly: 99000,
            features: ['Portfolio metrics', 'Tenant acquisition']
        );

        $response = $this->get(route('home'));

        $response->assertOk();

        $page = $this->extractSpaPage($response->getContent());

        $this->assertSame('welcome', $page['component']);
        $this->assertCount(2, $page['props']['subscriptionPlans']);
        $this->assertSame($starterPlan->name, $page['props']['subscriptionPlans'][0]['name']);
        $this->assertSame($growthPlan->name, $page['props']['subscriptionPlans'][1]['name']);
        $this->assertNull($page['props']['activeSubscription']);
    }

    public function test_welcome_page_includes_the_authenticated_users_active_subscription(): void
    {
        $user = new User;
        $user->name = 'Welcome Test User';
        $user->email = 'welcome-test@example.com';
        $user->email_verified_at = now();
        $user->password = Hash::make('password');
        $user->save();

        $subscriptionPlan = $this->createSubscriptionPlan(
            name: 'Portfolio',
            monthly: 14900,
            yearly: 149000,
            features: ['Multi-property oversight', 'Priority onboarding']
        );

        $userSubscriptionPlan = new UserSubscriptionPlan;
        $userSubscriptionPlan->user_id = $user->id;
        $userSubscriptionPlan->subscription_plan_id = $subscriptionPlan->id;
        $userSubscriptionPlan->start_date = now()->subDay();
        $userSubscriptionPlan->end_date = now()->addMonth();
        $userSubscriptionPlan->type = 'paid';
        $userSubscriptionPlan->status = 'active';
        $userSubscriptionPlan->amount_paid = 14900;
        $userSubscriptionPlan->billing_cycle = 'monthly';
        $userSubscriptionPlan->save();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();

        $page = $this->extractSpaPage($response->getContent());

        $this->assertSame('Portfolio', $page['props']['activeSubscription']['name']);
        $this->assertSame('monthly', $page['props']['activeSubscription']['billingCycle']);
        $this->assertSame(14900, $page['props']['activeSubscription']['price']['monthly']);
    }

    private function createSubscriptionPlan(
        string $name,
        int $monthly,
        int $yearly,
        array $features,
    ): SubscriptionPlan {
        $subscriptionPlan = new SubscriptionPlan;
        $subscriptionPlan->name = $name;
        $subscriptionPlan->description = "{$name} plan";
        $subscriptionPlan->price = [
            'monthly' => $monthly,
            'yearly' => $yearly,
        ];
        $subscriptionPlan->billing_cycle = 'monthly';
        $subscriptionPlan->max_properties = 10;
        $subscriptionPlan->max_units = 100;
        $subscriptionPlan->max_users = 10;
        $subscriptionPlan->features = $features;
        $subscriptionPlan->save();

        return $subscriptionPlan;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractSpaPage(string $html): array
    {
        preg_match('/window\.__SPA_PAGE__ = (.*?);\s*<\/script>/s', $html, $matches);

        $this->assertArrayHasKey(1, $matches);

        /** @var array<string, mixed>|null $page */
        $page = json_decode($matches[1], true);

        $this->assertIsArray($page);

        return $page;
    }
}
