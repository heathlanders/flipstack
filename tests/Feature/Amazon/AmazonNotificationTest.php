<?php

namespace Tests\Feature\Amazon;

use App\Http\Controllers\Amazon\AmazonNotificationController;
use App\Jobs\Amazon\ProcessAmazonNotification;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmazonNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;

    protected Organization $orgB;

    protected IntegrationCredential $credA;

    protected IntegrationCredential $credB;

    protected function setUp(): void
    {
        parent::setUp();

        // Bypass SNS signature check in tests via a partial-mock subclass.
        $this->app->bind(AmazonNotificationController::class, function () {
            return new class extends AmazonNotificationController
            {
                protected function verifySnsSignature(\Illuminate\Http\Request $request): bool
                {
                    return true;
                }
            };
        });

        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->credA = IntegrationCredential::create([
            'organization_id' => $this->orgA->id,
            'team_id' => Team::factory()->create(['organization_id' => $this->orgA->id])->id,
            'platform' => 'amazon',
            'environment' => 'sandbox',
            'name' => 'Org A Amazon',
            'is_active' => true,
            'config' => ['amazon_seller_id' => 'AAAA1111'],
        ]);

        $this->credB = IntegrationCredential::create([
            'organization_id' => $this->orgB->id,
            'team_id' => Team::factory()->create(['organization_id' => $this->orgB->id])->id,
            'platform' => 'amazon',
            'environment' => 'sandbox',
            'name' => 'Org B Amazon',
            'is_active' => true,
            'config' => ['amazon_seller_id' => 'BBBB2222'],
        ]);
    }

    public function test_subscription_confirmation_subscribes(): void
    {
        Http::fake([
            '*sns*amazonaws.com*' => Http::response('OK', 200),
        ]);

        $response = $this->withHeaders([
            'x-amz-sns-message-type' => 'SubscriptionConfirmation',
        ])->postJson(route('webhooks.amazon.handle'), [
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => 'sub-msg-1',
            'Token' => 'tok',
            'TopicArn' => 'arn:aws:sns:us-east-1:000000000000:test',
            'Message' => 'Confirm me',
            'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription&Token=tok',
            'Timestamp' => now()->toIso8601String(),
            'SignatureVersion' => '1',
            'Signature' => 'fake',
            'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
        ]);

        $response->assertOk();
        Http::assertSent(fn ($req) => str_contains($req->url(), 'sns.us-east-1.amazonaws.com'));
    }

    public function test_notification_routed_to_correct_tenant_by_seller_id(): void
    {
        Bus::fake([ProcessAmazonNotification::class]);

        $payload = [
            'Type' => 'Notification',
            'MessageId' => 'unique-msg-1',
            'TopicArn' => 'arn:aws:sns:us-east-1:000000000000:test',
            'Message' => json_encode([
                'NotificationType' => 'ORDER_CHANGE',
                'Payload' => [
                    'OrderChangeNotification' => [
                        'AmazonOrderId' => '111-1234567-1234567',
                        'SellerId' => 'BBBB2222',
                    ],
                ],
            ]),
            'Timestamp' => now()->toIso8601String(),
            'SignatureVersion' => '1',
            'Signature' => 'fake',
            'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
        ];

        $response = $this->withHeaders([
            'x-amz-sns-message-type' => 'Notification',
        ])->postJson(route('webhooks.amazon.handle'), $payload);

        $response->assertOk();

        Bus::assertDispatched(ProcessAmazonNotification::class, function ($job) {
            $reflection = new \ReflectionClass($job);
            $credProp = $reflection->getProperty('credential');
            $credProp->setAccessible(true);
            $cred = $credProp->getValue($job);

            return $cred->id === $this->credB->id;
        });
    }

    public function test_unknown_seller_id_does_not_dispatch(): void
    {
        Bus::fake([ProcessAmazonNotification::class]);

        $payload = [
            'Type' => 'Notification',
            'MessageId' => 'unique-msg-2',
            'TopicArn' => 'arn:aws:sns:us-east-1:000000000000:test',
            'Message' => json_encode([
                'NotificationType' => 'ORDER_CHANGE',
                'Payload' => [
                    'OrderChangeNotification' => [
                        'AmazonOrderId' => '111-7777777-7777777',
                        'SellerId' => 'UNKNOWN',
                    ],
                ],
            ]),
            'Timestamp' => now()->toIso8601String(),
            'SignatureVersion' => '1',
            'Signature' => 'fake',
            'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
        ];

        $response = $this->withHeaders([
            'x-amz-sns-message-type' => 'Notification',
        ])->postJson(route('webhooks.amazon.handle'), $payload);

        $response->assertOk();
        Bus::assertNotDispatched(ProcessAmazonNotification::class);
    }

    public function test_duplicate_message_id_skips_dispatch(): void
    {
        Bus::fake([ProcessAmazonNotification::class]);
        Cache::flush();

        $payload = [
            'Type' => 'Notification',
            'MessageId' => 'dedup-msg-1',
            'TopicArn' => 'arn:aws:sns:us-east-1:000000000000:test',
            'Message' => json_encode([
                'NotificationType' => 'ORDER_CHANGE',
                'Payload' => [
                    'OrderChangeNotification' => [
                        'AmazonOrderId' => '111-9999999-9999999',
                        'SellerId' => 'AAAA1111',
                    ],
                ],
            ]),
            'Timestamp' => now()->toIso8601String(),
            'SignatureVersion' => '1',
            'Signature' => 'fake',
            'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
        ];

        // First delivery should dispatch.
        $this->withHeaders(['x-amz-sns-message-type' => 'Notification'])
            ->postJson(route('webhooks.amazon.handle'), $payload)
            ->assertOk();

        // Second delivery with same MessageId should skip.
        $this->withHeaders(['x-amz-sns-message-type' => 'Notification'])
            ->postJson(route('webhooks.amazon.handle'), $payload)
            ->assertOk();

        Bus::assertDispatchedTimes(ProcessAmazonNotification::class, 1);
    }
}
