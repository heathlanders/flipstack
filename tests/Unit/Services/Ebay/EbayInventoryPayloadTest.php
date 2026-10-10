<?php

namespace Tests\Unit\Services\Ebay;

use App\Services\EbayInventoryService;
use ReflectionClass;
use Tests\TestCase;

class EbayInventoryPayloadTest extends TestCase
{
    private function buildPayload(array $data): array
    {
        // Bypass the constructor (which loads eBay credentials).
        $service = (new ReflectionClass(EbayInventoryService::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('buildInventoryItemPayload');
        $method->setAccessible(true);

        return $method->invoke($service, $data);
    }

    public function test_omits_aspects_when_none_resolved(): void
    {
        // No brand/mpn/specs -> buildAspects returns []. The key must be ABSENT,
        // not an empty array (eBay rejects "aspects": [] as unserializable).
        $payload = $this->buildPayload([
            'title' => 'PROTECH 62-24164-01 Flame Sensor',
            'description' => 'desc',
            'condition' => 'NEW',
            'quantity' => 1,
            'images' => ['queue-photos-processed/a.png'],
        ]);

        $this->assertArrayNotHasKey('aspects', $payload['product']);

        // And it must serialize without an empty array for aspects.
        $this->assertStringNotContainsString('"aspects":[]', json_encode($payload));
    }

    public function test_includes_aspects_as_object_when_present(): void
    {
        $payload = $this->buildPayload([
            'title' => 'Widget',
            'description' => 'desc',
            'condition' => 'NEW',
            'quantity' => 1,
            'brand' => 'PROTECH',
            'mpn' => '62-24164-01',
        ]);

        $this->assertArrayHasKey('aspects', $payload['product']);
        $this->assertSame(['PROTECH'], $payload['product']['aspects']['Brand']);
        // Non-empty associative array serializes as a JSON object.
        $this->assertStringContainsString('"aspects":{', json_encode($payload));
    }
}
