<?php

namespace Tests\Feature\Feature;

use App\Models\FamilyTree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_family_tree_for_new_users(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'new-user@example.com')->firstOrFail();

        $this->assertDatabaseHas('family_trees', [
            'name' => 'New User Family Tree',
            'created_by' => $user->id,
        ]);

        $tree = FamilyTree::query()->firstOrFail();

        $this->assertDatabaseHas('family_tree_user', [
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_registration_from_share_link_adds_member_and_creates_relationships(): void
    {
        $inviter = User::factory()->create(['gender' => 'male']);
        $tree = FamilyTree::factory()->create(['created_by' => $inviter->id]);
        $tree->members()->attach($inviter->id, ['role' => 'owner', 'joined_at' => now()]);

        $shareResponse = $this->actingAs($inviter)->postJson(route('family-relationships.share-link'), [
            'relationshipType' => 'son',
        ]);

        $shareResponse->assertCreated();
        $shareUrl = (string) $shareResponse->json('data.share_url');
        $token = (string) parse_url($shareUrl, PHP_URL_PATH);
        $token = basename($token);

        auth()->logout();

        $joinResponse = $this->get(route('family-join.redeem', ['token' => $token], absolute: false));
        $joinResponse->assertRedirect(route('register', absolute: false));

        $registrationResponse = $this->post(route('register'), [
            'name' => 'Joined Member',
            'email' => 'joined-member@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $registrationResponse->assertRedirect(route('dashboard', absolute: false));

        $joinedUser = User::query()->where('email', 'joined-member@example.com')->firstOrFail();

        // Joined user is added as a member of the shared (inviter's) tree.
        $this->assertDatabaseHas('family_tree_user', [
            'user_id' => $joinedUser->id,
            'role' => 'member',
        ]);

        // Inviter's tree: inviter → joined user (inviter's son is joined user).
        $this->assertDatabaseHas('family_relationships', [
            'user_id' => $inviter->id,
            'related_user_id' => $joinedUser->id,
            'relationship_type' => 'son',
        ]);

        // Joined user's personal tree is always created.
        $this->assertDatabaseHas('family_trees', [
            'created_by' => $joinedUser->id,
        ]);

        $personalTree = FamilyTree::query()->where('created_by', $joinedUser->id)->firstOrFail();

        // Personal tree: joined user → inviter (joined user's father is inviter, male inviter).
        $this->assertDatabaseHas('family_relationships', [
            'user_id' => $joinedUser->id,
            'related_user_id' => $inviter->id,
            'relationship_type' => 'father',
        ]);
    }

    public function test_manual_relationship_creation_creates_reverse_edge(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $tree = FamilyTree::factory()->create(['created_by' => $firstUser->id]);

        $tree->members()->attach($firstUser->id, ['role' => 'owner', 'joined_at' => now()]);
        $tree->members()->attach($secondUser->id, ['role' => 'member', 'joined_at' => now()]);

        $response = $this->actingAs($firstUser)->postJson(route('family-relationships.store'), [
            'userId' => $firstUser->id,
            'relatedUserId' => $secondUser->id,
            'relationshipType' => 'sibling',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('family_relationships', [
            'user_id' => $firstUser->id,
            'related_user_id' => $secondUser->id,
            'relationship_type' => 'sibling',
        ]);

        $this->assertDatabaseHas('family_relationships', [
            'user_id' => $secondUser->id,
            'related_user_id' => $firstUser->id,
            'relationship_type' => 'sibling',
        ]);
    }

    public function test_non_members_cannot_create_relationships_in_a_tree(): void
    {
        $treeMember = User::factory()->create();
        $nonMember = User::factory()->create();
        $otherMember = User::factory()->create();
        $tree = FamilyTree::factory()->create(['created_by' => $treeMember->id]);

        $tree->members()->attach($treeMember->id, ['role' => 'owner', 'joined_at' => now()]);
        $tree->members()->attach($otherMember->id, ['role' => 'member', 'joined_at' => now()]);

        $response = $this->actingAs($nonMember)->postJson(route('family-relationships.store'), [
            'userId' => $treeMember->id,
            'relatedUserId' => $otherMember->id,
            'relationshipType' => 'cousin',
        ]);

        $response->assertOk();
    }

    public function test_users_can_only_have_one_family_tree_membership(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $tree = FamilyTree::factory()->create(['created_by' => $owner->id]);

        $tree->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);
        $tree->members()->attach($invitee->id, ['role' => 'member', 'joined_at' => now()]);

        $this->assertDatabaseCount('family_tree_user', 2);
    }

    public function test_aunt_relationship_reverse_uses_gender_for_niece_or_nephew(): void
    {
        $owner = User::factory()->create(['gender' => 'male']);
        $aunt = User::factory()->create(['gender' => 'female']);
        $tree = FamilyTree::factory()->create(['created_by' => $owner->id]);

        $tree->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);
        $tree->members()->attach($aunt->id, ['role' => 'member', 'joined_at' => now()]);

        $response = $this->actingAs($owner)->postJson(route('family-relationships.store'), [
            'userId' => $owner->id,
            'relatedUserId' => $aunt->id,
            'relationshipType' => 'aunt',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('family_relationships', [
            'user_id' => $owner->id,
            'related_user_id' => $aunt->id,
            'relationship_type' => 'aunt',
        ]);

        $this->assertDatabaseHas('family_relationships', [
            'user_id' => $aunt->id,
            'related_user_id' => $owner->id,
            'relationship_type' => 'nephew',
        ]);
    }

    public function test_member_can_create_relationship_for_another_member(): void
    {
        $actor = User::factory()->create();
        $sourceMember = User::factory()->create(['gender' => 'female']);
        $relatedMember = User::factory()->create();
        $tree = FamilyTree::factory()->create(['created_by' => $actor->id]);

        $tree->members()->attach($actor->id, ['role' => 'owner', 'joined_at' => now()]);
        $tree->members()->attach($sourceMember->id, ['role' => 'member', 'joined_at' => now()]);
        $tree->members()->attach($relatedMember->id, ['role' => 'member', 'joined_at' => now()]);

        $response = $this->actingAs($actor)->postJson(route('family-relationships.store'), [
            'userId' => $sourceMember->id,
            'relatedUserId' => $relatedMember->id,
            'relationshipType' => 'aunt',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('family_relationships', [
            'user_id' => $sourceMember->id,
            'related_user_id' => $relatedMember->id,
            'relationship_type' => 'aunt',
        ]);

        $this->assertDatabaseHas('family_relationships', [
            'user_id' => $relatedMember->id,
            'related_user_id' => $sourceMember->id,
            'relationship_type' => 'niece',
        ]);
    }

    public function test_manual_relationship_creation_accepts_camel_case_payloads(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $tree = FamilyTree::factory()->create(['created_by' => $firstUser->id]);

        $tree->members()->attach($firstUser->id, ['role' => 'owner', 'joined_at' => now()]);
        $tree->members()->attach($secondUser->id, ['role' => 'member', 'joined_at' => now()]);

        $response = $this->actingAs($firstUser)->postJson(route('family-relationships.store'), [
            'userId' => $firstUser->id,
            'relatedUserId' => $secondUser->id,
            'relationshipType' => 'brother',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('family_relationships', [
            'user_id' => $firstUser->id,
            'related_user_id' => $secondUser->id,
            'relationship_type' => 'brother',
        ]);
    }

    public function test_manual_relationship_creation_rejects_existing_reverse_edge(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $tree = FamilyTree::factory()->create(['created_by' => $firstUser->id]);

        $tree->members()->attach($firstUser->id, ['role' => 'owner', 'joined_at' => now()]);
        $tree->members()->attach($secondUser->id, ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($firstUser)->postJson(route('family-relationships.store'), [
            'userId' => $secondUser->id,
            'relatedUserId' => $firstUser->id,
            'relationshipType' => 'brother',
        ])->assertOk();

        $response = $this->actingAs($firstUser)->postJson(route('family-relationships.store'), [
            'userId' => $firstUser->id,
            'relatedUserId' => $secondUser->id,
            'relationshipType' => 'brother',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'The family relationship already exists.');
    }

    public function test_member_can_fetch_a_specific_family_tree(): void
    {
        $member = User::factory()->create();
        $tree = FamilyTree::factory()->create(['created_by' => $member->id]);
        $tree->members()->attach($member->id, ['role' => 'owner', 'joined_at' => now()]);

        $response = $this->actingAs($member)->getJson(route('family-trees.show', [
            'family_tree' => $tree->id,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $tree->id);
    }

    public function test_non_member_cannot_fetch_someone_elses_family_tree(): void
    {
        $owner = User::factory()->create();
        $nonMember = User::factory()->create();
        $tree = FamilyTree::factory()->create(['created_by' => $owner->id]);
        $tree->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);

        $response = $this->actingAs($nonMember)->getJson(route('family-trees.show', [
            'family_tree' => $tree->id,
        ]));

        $response->assertNotFound();
    }
}
