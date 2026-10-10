<?php

namespace Tests\Feature\Communication;

use App\Models\Catalog\Product;
use App\Models\Communication\MarketplaceMessage;
use App\Models\Ebay\EbayListing;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use App\Notifications\Communication\NewMessageNotification;
use App\Services\Communication\EbayMessageService;
use App\Services\Communication\MessageSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create([
            'sku_prefix' => 'MSG',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-msg',
            'is_system' => true,
            'permissions' => [
                'inventory.view', 'messages.view', 'messages.reply',
            ],
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($adminRole->id, [
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
    }

    public function test_can_view_message_inbox(): void
    {
        MarketplaceMessage::factory()->fromEbay()->create([
            'organization_id' => $this->organization->id,
            'subject' => 'Question about item',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('messages.inbox'));

        $response->assertStatus(200);
    }

    public function test_can_view_message_thread(): void
    {
        $message = MarketplaceMessage::factory()->fromEbay()->create([
            'organization_id' => $this->organization->id,
            'subject' => 'Shipping inquiry',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('messages.thread', $message));

        $response->assertStatus(200);
    }

    public function test_messages_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();

        MarketplaceMessage::factory()->create([
            'organization_id' => $otherOrg->id,
            'subject' => 'Other org message',
        ]);

        $myMessage = MarketplaceMessage::factory()->create([
            'organization_id' => $this->organization->id,
            'subject' => 'My org message',
        ]);

        $myMessages = MarketplaceMessage::where('organization_id', $this->organization->id)->get();

        $this->assertCount(1, $myMessages);
        $this->assertEquals('My org message', $myMessages->first()->subject);
    }

    public function test_inbox_shows_product_data(): void
    {
        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Widget',
            'sku' => 'TW-001',
        ]);

        MarketplaceMessage::factory()->fromEbay()->create([
            'organization_id' => $this->organization->id,
            'product_id' => $product->id,
            'subject' => 'Question about widget',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('messages.inbox'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('messages.data.0.product')
        );
    }

    public function test_thread_shows_product_data(): void
    {
        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Widget',
            'sku' => 'TW-001',
        ]);

        $message = MarketplaceMessage::factory()->fromEbay()->create([
            'organization_id' => $this->organization->id,
            'product_id' => $product->id,
            'subject' => 'Question about widget',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('messages.thread', $message));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('message.product')
        );
    }

    public function test_upsert_links_product_via_ebay_listing(): void
    {
        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'platform' => 'ebay',
            'is_active' => true,
            'environment' => 'production',
        ]);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Linked Product',
        ]);

        EbayListing::create([
            'integration_credential_id' => $credential->id,
            'product_id' => $product->id,
            'team_id' => $this->team->id,
            'ebay_listing_id' => '123456789',
            'ebay_sku' => 'TEST-SKU-001',
            'status' => 'published',
            'sold_quantity' => 0,
            'consecutive_errors' => 0,
        ]);

        $service = new MessageSyncService;
        $method = new \ReflectionMethod($service, 'upsertMessage');

        $msgData = [
            'marketplace_message_id' => 'MSG-TEST-001',
            'marketplace_thread_id' => 'THREAD-001',
            'marketplace_item_id' => '123456789',
            'message_type' => 'AskSellerQuestion',
            'direction' => 'inbound',
            'subject' => 'Is this still available?',
            'body' => 'Hello, is this item still available?',
            'sender_name' => 'Test Buyer',
            'sender_id' => 'buyer123',
            'received_at' => now(),
        ];

        $message = $method->invoke($service, $msgData, $this->organization->id, $credential);

        $this->assertNotNull($message);
        $this->assertInstanceOf(MarketplaceMessage::class, $message);
        $this->assertEquals($product->id, $message->product_id);
    }

    public function test_upsert_without_item_id_has_null_product(): void
    {
        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'platform' => 'ebay',
            'is_active' => true,
            'environment' => 'production',
        ]);

        $service = new MessageSyncService;
        $method = new \ReflectionMethod($service, 'upsertMessage');

        $msgData = [
            'marketplace_message_id' => 'MSG-TEST-002',
            'marketplace_thread_id' => 'THREAD-002',
            'marketplace_item_id' => '',
            'message_type' => 'ContactEbayMember',
            'direction' => 'inbound',
            'subject' => 'General question',
            'body' => 'Hello there',
            'sender_name' => 'Test Buyer',
            'sender_id' => 'buyer456',
            'received_at' => now(),
        ];

        $message = $method->invoke($service, $msgData, $this->organization->id, $credential);

        $this->assertNotNull($message);
        $this->assertNull($message->product_id);
    }

    public function test_sync_continues_after_individual_message_error(): void
    {
        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'platform' => 'ebay',
            'is_active' => true,
            'environment' => 'production',
        ]);

        $service = new MessageSyncService;
        $method = new \ReflectionMethod($service, 'upsertMessage');

        // First message succeeds
        $msg1 = [
            'marketplace_message_id' => 'MSG-OK',
            'marketplace_thread_id' => 'T1',
            'marketplace_item_id' => '',
            'message_type' => null,
            'direction' => 'inbound',
            'subject' => 'Good message',
            'body' => 'This should succeed',
            'sender_name' => 'Buyer',
            'sender_id' => 'b1',
            'received_at' => now(),
        ];

        $message = $method->invoke($service, $msg1, $this->organization->id, $credential);
        $this->assertNotNull($message);

        $this->assertDatabaseHas('marketplace_messages', [
            'marketplace_message_id' => 'MSG-OK',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_new_inbound_message_triggers_notification(): void
    {
        Notification::fake();

        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'platform' => 'ebay',
            'is_active' => true,
            'environment' => 'production',
        ]);

        $service = new MessageSyncService;
        $upsertMethod = new \ReflectionMethod($service, 'upsertMessage');
        $notifyMethod = new \ReflectionMethod($service, 'notifyNewMessages');

        $msgData = [
            'marketplace_message_id' => 'MSG-NOTIFY-001',
            'marketplace_thread_id' => 'T-NOTIFY',
            'marketplace_item_id' => '',
            'message_type' => 'AskSellerQuestion',
            'direction' => 'inbound',
            'subject' => 'Question about shipping',
            'body' => 'How long does shipping take?',
            'sender_name' => 'Curious Buyer',
            'sender_id' => 'buyer789',
            'received_at' => now(),
        ];

        $message = $upsertMethod->invoke($service, $msgData, $this->organization->id, $credential);
        $this->assertNotNull($message);

        $notifyMethod->invoke($service, [$message], $this->organization->id);

        Notification::assertSentTo(
            $this->user,
            NewMessageNotification::class,
            function ($notification) {
                return $notification->marketplaceMessage->marketplace_message_id === 'MSG-NOTIFY-001';
            }
        );
    }

    public function test_extract_message_body_from_ebay_html(): void
    {
        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'platform' => 'ebay',
            'is_active' => true,
            'environment' => 'production',
        ]);

        $service = new EbayMessageService($credential);
        $method = new \ReflectionMethod($service, 'extractMessageBody');

        // HTML with UserInputtedText div
        $html = '<html><head><style>body{color:red}</style></head><body>'
            .'<table><tr><td><div id="UserInputtedText">Hey would u do 200$</div></td></tr></table>'
            .'<div id="UserInputtedText1">Previous message here</div>'
            .'</body></html>';

        $result = $method->invoke($service, $html);
        $this->assertEquals('Hey would u do 200$', $result);

        // HTML with br tags in message
        $htmlWithBr = '<div id="UserInputtedText">Line one<br/>Line two<br />Line three</div>';
        $result = $method->invoke($service, $htmlWithBr);
        $this->assertEquals("Line one\nLine two\nLine three", $result);

        // Plain text (no HTML) passes through
        $plain = 'Just a plain text message';
        $result = $method->invoke($service, $plain);
        $this->assertEquals('Just a plain text message', $result);

        // Empty string
        $result = $method->invoke($service, '');
        $this->assertEquals('', $result);
    }
}
