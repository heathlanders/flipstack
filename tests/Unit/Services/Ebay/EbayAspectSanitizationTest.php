<?php

namespace Tests\Unit\Services\Ebay;

use App\Services\EbayInventoryService;
use ReflectionClass;
use Tests\TestCase;

class EbayAspectSanitizationTest extends TestCase
{
    private function sanitize(string $value): string
    {
        // Bypass the constructor (which loads eBay credentials) -- we only exercise
        // the pure string sanitizer.
        $service = (new ReflectionClass(EbayInventoryService::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('sanitizeAspectValue');
        $method->setAccessible(true);

        return $method->invoke($service, $value);
    }

    public function test_strips_parenthetical_explanation_from_non_answer(): void
    {
        $this->assertSame(
            'Not specified',
            $this->sanitize('Not specified (typically low current for ignitors, controlled by furnace board)'),
        );

        $this->assertSame(
            'Unknown',
            $this->sanitize("Unknown (typically covered by seller's return policy or limited OEM warranty)"),
        );
    }

    public function test_caps_long_real_value_at_65_characters(): void
    {
        $long = str_repeat('Heavy Duty Stainless Steel ', 5); // ~135 chars, no parens
        $result = $this->sanitize($long);

        $this->assertLessThanOrEqual(65, mb_strlen($result));
        $this->assertNotEmpty($result);
    }

    public function test_leaves_short_values_untouched(): void
    {
        $this->assertSame('16 GB', $this->sanitize('16 GB'));
        $this->assertSame('Intel Core i7-1365U', $this->sanitize('Intel Core i7-1365U'));
    }

    public function test_returns_empty_for_blank_value(): void
    {
        $this->assertSame('', $this->sanitize('   '));
    }
}
