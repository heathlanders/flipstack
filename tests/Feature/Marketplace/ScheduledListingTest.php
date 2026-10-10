<?php

namespace Tests\Feature\Marketplace;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Marketplace\ScheduledListing;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class ScheduledListingTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected Role $adminRole;

    protected Product $product;

    protected IntegrationCredential $credential;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'permissions' => [
                'inventory.view', 'products.view', 'products.create',
                'marketplace.view', 'marketplace.manage',
            ],
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($this->adminRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        $this->user->current_team_id = $this->team->id;
        $this->user->save();

        $this->team->users()->attach($this->user->id, ['role' => 'admin']);

        OnboardingProgress::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'current_step' => 'completed',
            'steps_completed' => ['welcome', 'profile', 'organization', 'team'],
        ]);

        $category = Category::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $category->id,
            'created_by' => $this->user->id,
        ]);

        $this->credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'ebay',
            'environment' => 'production',
            'name' => 'eBay Test Account',
            'is_active' => true,
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);
    }

    public function test_can_view_scheduled_listings(): void
    {
        ScheduledListing::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'integration_credential_id' => $this->credential->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('marketplaces.scheduled-listings.index'));

        $response->assertStatus(200);
    }

    public function test_can_create_scheduled_listing(): void
    {
        $listingData = json_encode([
            'title' => 'Test Scheduled Listing',
            'price' => 29.99,
            'quantity' => 5,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('marketplaces.scheduled-listings.store'), [
                'product_id' => $this->product->id,
                'marketplace' => 'ebay',
                'integration_credential_id' => $this->credential->id,
                'scheduled_at' => now()->addDays(3)->toDateTimeString(),
                'listing_data' => $listingData,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('scheduled_listings', [
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'marketplace' => 'ebay',
            'status' => ScheduledListing::STATUS_PENDING,
        ]);
    }

    public function test_scheduled_listing_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();

        $myListing = ScheduledListing::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'integration_credential_id' => $this->credential->id,
            'created_by' => $this->user->id,
        ]);

        $otherCategory = Category::factory()->create(['organization_id' => $otherOrg->id]);
        $otherProduct = Product::factory()->create([
            'organization_id' => $otherOrg->id,
            'category_id' => $otherCategory->id,
        ]);
        $otherCredential = IntegrationCredential::create([
            'organization_id' => $otherOrg->id,
            'platform' => 'ebay',
            'environment' => 'production',
            'name' => 'Other eBay Account',
            'is_active' => true,
            'client_id' => 'other-client-id',
            'client_secret' => 'other-client-secret',
        ]);
        $otherListing = ScheduledListing::factory()->create([
            'organization_id' => $otherOrg->id,
            'product_id' => $otherProduct->id,
            'integration_credential_id' => $otherCredential->id,
        ]);

        $orgListings = ScheduledListing::where('organization_id', $this->organization->id)->get();

        $this->assertTrue($orgListings->contains($myListing));
        $this->assertFalse($orgListings->contains($otherListing));
    }
}
