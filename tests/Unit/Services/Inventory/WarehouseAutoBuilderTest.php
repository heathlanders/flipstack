<?php

namespace Tests\Unit\Services\Inventory;

use App\Services\Inventory\WarehouseAutoBuilder;
use PHPUnit\Framework\TestCase;

class WarehouseAutoBuilderTest extends TestCase
{
    protected WarehouseAutoBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new WarehouseAutoBuilder;
    }

    public function test_bin_code_shelf_and_tub(): void
    {
        $code = $this->builder->generateBinCode('44', 'tub', 'LABRADOR', null);
        $this->assertEquals('SHLF-44-TUB-LABRADOR', $code);
    }

    public function test_bin_code_shelf_and_level(): void
    {
        $code = $this->builder->generateBinCode('28', null, null, '4');
        $this->assertEquals('SHLF-28-LVL-4', $code);
    }

    public function test_bin_code_shelf_only(): void
    {
        $code = $this->builder->generateBinCode('BO', null, null, null);
        $this->assertEquals('SHLF-BO', $code);
    }

    public function test_bin_code_box_only(): void
    {
        $code = $this->builder->generateBinCode(null, 'box', 'TRAPMAXXING', null);
        $this->assertEquals('BOX-TRAPMAXXING', $code);
    }

    public function test_bin_code_shelf_level_and_tub(): void
    {
        $code = $this->builder->generateBinCode('M', 'tub', 'CIRCUS', '3');
        $this->assertEquals('SHLF-M-LVL-3-TUB-CIRCUS', $code);
    }

    public function test_bin_code_ground_level(): void
    {
        $code = $this->builder->generateBinCode('M', null, null, 'GROUND');
        $this->assertEquals('SHLF-M-LVL-GND', $code);
    }

    public function test_bin_code_no_components_returns_default(): void
    {
        $code = $this->builder->generateBinCode(null, null, null, null);
        $this->assertEquals('DEFAULT', $code);
    }

    public function test_bin_code_uppercases_shelf(): void
    {
        $code = $this->builder->generateBinCode('bo', null, null, null);
        $this->assertEquals('SHLF-BO', $code);
    }
}
