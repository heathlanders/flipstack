<?php

namespace Tests\Unit\Intake;

use App\Http\Controllers\Product\IntakeQueueController;
use App\Models\Catalog\ProductIntakeQueue;
use ReflectionClass;
use Tests\TestCase;

class BuildIntakeAttributesTest extends TestCase
{
    private function build(ProductIntakeQueue $queue, array $userSpecs = []): array
    {
        $controller = (new ReflectionClass(IntakeQueueController::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass($controller))->getMethod('buildIntakeAttributes');
        $method->setAccessible(true);

        return $method->invoke($controller, $queue, $userSpecs);
    }

    public function test_persists_identity_and_merged_specifications(): void
    {
        $queue = new ProductIntakeQueue;
        $queue->enriched_data = [
            'merged_product_fields' => [
                'product_identity' => [
                    'brand' => 'PROTECH',
                    'manufacturer_part_number' => '62-24164-01',
                    'model' => 'DSI Ignitor',
                ],
                'product_details' => [
                    'specifications' => ['Voltage' => '120 V', 'Material' => 'Ceramic'],
                ],
            ],
        ];

        $attrs = $this->build($queue);

        $this->assertSame('PROTECH', $attrs['brand']);
        $this->assertSame('62-24164-01', $attrs['mpn']);
        $this->assertSame('DSI Ignitor', $attrs['model']);
        $this->assertSame('120 V', $attrs['specifications']['Voltage']);
        $this->assertSame('Ceramic', $attrs['specifications']['Material']);
    }

    public function test_user_specifics_override_ai(): void
    {
        $queue = new ProductIntakeQueue;
        $queue->enriched_data = [
            'merged_product_fields' => [
                'product_details' => ['specifications' => ['Type' => 'AI Guess', 'Voltage' => '120 V']],
            ],
        ];

        $attrs = $this->build($queue, ['Type' => 'Burner Control']);

        // User value wins; AI-only keys are retained.
        $this->assertSame('Burner Control', $attrs['specifications']['Type']);
        $this->assertSame('120 V', $attrs['specifications']['Voltage']);
    }

    public function test_empty_enriched_data_yields_empty_attributes(): void
    {
        $queue = new ProductIntakeQueue;
        $queue->enriched_data = [];

        $this->assertSame([], $this->build($queue));
    }
}
