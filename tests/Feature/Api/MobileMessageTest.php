<?php

namespace Tests\Feature\Api;

use App\Models\Communication\MarketplaceMessage;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AssertsAudits;
use Tests\TestCase;

class MobileMessageTest extends TestCase
{
    use AssertsAudits;
    use RefreshDatabase;

    protected Organization $organization;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        Sanctum::actingAs($this->user, ['*']);
    }

    private function message(array $attrs = []): MarketplaceMessage
    {
        return MarketplaceMessage::factory()->fromEbay()->create(array_merge([
            'organization_id' => $this->organization->id,
            'status' => MarketplaceMessage::STATUS_UNREAD,
        ], $attrs));
    }

    public function test_index_lists_messages_with_stats(): void
    {
        $this->message(['subject' => 'Where is my order?']);

        $this->getJson(route('api.messages.index'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.subject', 'Where is my order?')
            ->assertJsonPath('stats.unread', 1);
    }

    public function test_show_returns_thread_and_marks_read(): void
    {
        $msg = $this->message();

        $this->getJson(route('api.messages.show', $msg))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message.id', $msg->id);

        $this->assertDatabaseHas('marketplace_messages', [
            'id' => $msg->id,
            'status' => MarketplaceMessage::STATUS_READ,
        ]);
    }

    public function test_reply_stores_reply_and_audits_mobile_source(): void
    {
        $msg = $this->message();

        $this->postJson(route('api.messages.reply', $msg), [
            'body' => 'Your order ships tomorrow.',
        ])->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('marketplace_message_replies', [
            'message_id' => $msg->id,
            'body' => 'Your order ships tomorrow.',
        ]);
        $this->assertDatabaseHas('marketplace_messages', [
            'id' => $msg->id,
            'status' => MarketplaceMessage::STATUS_REPLIED,
        ]);

        $this->assertAudited('message.replied', $this->user, ['source' => 'mobile']);
    }

    public function test_reply_validates_body(): void
    {
        $msg = $this->message();

        $this->postJson(route('api.messages.reply', $msg), ['body' => ''])
            ->assertStatus(422);
    }

    public function test_cannot_view_other_orgs_message(): void
    {
        $otherOrg = Organization::factory()->create();
        $msg = MarketplaceMessage::factory()->fromEbay()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $this->getJson(route('api.messages.show', $msg))->assertStatus(404);
    }
}
