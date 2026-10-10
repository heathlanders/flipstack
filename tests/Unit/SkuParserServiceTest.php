<?php

namespace Tests\Unit;

use App\Services\SkuParserService;
use PHPUnit\Framework\TestCase;

class SkuParserServiceTest extends TestCase
{
    protected SkuParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SkuParserService;
    }

    public function test_disabled_config_returns_null(): void
    {
        $result = $this->parser->parse('ABC 123 SHELF-A', ['enabled' => false, 'mode' => 'template']);
        $this->assertNull($result);
    }

    public function test_empty_sku_returns_null(): void
    {
        $result = $this->parser->parse('', ['enabled' => true, 'mode' => 'template', 'template' => ['type' => 'delimiter', 'delimiter' => ' ', 'segments' => []]]);
        $this->assertNull($result);
    }

    public function test_delimiter_parsing_space(): void
    {
        $config = [
            'enabled' => true,
            'mode' => 'template',
            'template' => [
                'type' => 'delimiter',
                'delimiter' => ' ',
                'segments' => [
                    ['field' => 'owner', 'position' => 0],
                    ['field' => 'sku', 'position' => 1],
                    ['field' => 'location', 'position' => 2],
                ],
            ],
        ];

        $result = $this->parser->parse('JONES SKU001 SHELF-A3', $config);
        $this->assertEquals('JONES', $result['owner']);
        $this->assertEquals('SKU001', $result['sku']);
        $this->assertEquals('SHELF-A3', $result['location']);
    }

    public function test_delimiter_parsing_dash(): void
    {
        $config = [
            'enabled' => true,
            'mode' => 'template',
            'template' => [
                'type' => 'delimiter',
                'delimiter' => '-',
                'segments' => [
                    ['field' => 'owner', 'position' => 0],
                    ['field' => 'sku', 'position' => 1],
                    ['field' => 'location', 'position' => 2],
                ],
            ],
        ];

        $result = $this->parser->parse('OWN-12345-LOC', $config);
        $this->assertEquals('OWN', $result['owner']);
        $this->assertEquals('12345', $result['sku']);
        $this->assertEquals('LOC', $result['location']);
    }

    public function test_delimiter_parsing_pipe(): void
    {
        $config = [
            'enabled' => true,
            'mode' => 'template',
            'template' => [
                'type' => 'delimiter',
                'delimiter' => '|',
                'segments' => [
                    ['field' => 'sku', 'position' => 0],
                    ['field' => 'owner', 'position' => 1],
                ],
            ],
        ];

        $result = $this->parser->parse('SKU999|SMITH', $config);
        $this->assertEquals('SMITH', $result['owner']);
        $this->assertEquals('SKU999', $result['sku']);
        $this->assertNull($result['location']);
    }

    public function test_delimiter_missing_segment_returns_null_for_field(): void
    {
        $config = [
            'enabled' => true,
            'mode' => 'template',
            'template' => [
                'type' => 'delimiter',
                'delimiter' => ' ',
                'segments' => [
                    ['field' => 'owner', 'position' => 0],
                    ['field' => 'sku', 'position' => 1],
                    ['field' => 'location', 'position' => 5],
                ],
            ],
        ];

        $result = $this->parser->parse('JONES SKU001', $config);
        $this->assertEquals('JONES', $result['owner']);
        $this->assertEquals('SKU001', $result['sku']);
        $this->assertNull($result['location']);
    }

    public function test_fixed_width_parsing(): void
    {
        $config = [
            'enabled' => true,
            'mode' => 'template',
            'template' => [
                'type' => 'fixed',
                'ranges' => [
                    ['field' => 'owner', 'start' => 0, 'length' => 5],
                    ['field' => 'sku', 'start' => 5, 'length' => 10],
                    ['field' => 'location', 'start' => 15, 'length' => 10],
                ],
            ],
        ];

        $result = $this->parser->parse('JONESSKU0012345SHELF-A   ', $config);
        $this->assertEquals('JONES', $result['owner']);
        $this->assertEquals('SKU0012345', $result['sku']);
        $this->assertEquals('SHELF-A', $result['location']);
    }

    public function test_fixed_width_short_string(): void
    {
        $config = [
            'enabled' => true,
            'mode' => 'template',
            'template' => [
                'type' => 'fixed',
                'ranges' => [
                    ['field' => 'owner', 'start' => 0, 'length' => 5],
                    ['field' => 'sku', 'start' => 5, 'length' => 10],
                    ['field' => 'location', 'start' => 50, 'length' => 10],
                ],
            ],
        ];

        $result = $this->parser->parse('ABCDE12345', $config);
        $this->assertEquals('ABCDE', $result['owner']);
        $this->assertEquals('12345', $result['sku']);
        $this->assertNull($result['location']);
    }

    public function test_regex_parsing_with_named_groups(): void
    {
        $config = [
            'enabled' => true,
            'mode' => 'regex',
            'regex' => [
                'pattern' => '/^(?P<owner>\S+)\s+(?P<sku>\S+)\s+(?P<location>.+)$/',
            ],
        ];

        $result = $this->parser->parse('SMITH SKU-100 Warehouse B Shelf 3', $config);
        $this->assertEquals('SMITH', $result['owner']);
        $this->assertEquals('SKU-100', $result['sku']);
        $this->assertEquals('Warehouse B Shelf 3', $result['location']);
    }

    public function test_regex_no_match_returns_nulls(): void
    {
        $config = [
            'enabled' => true,
            'mode' => 'regex',
            'regex' => [
                'pattern' => '/^(?P<owner>[A-Z]{3})-(?P<sku>\d+)$/',
            ],
        ];

        $result = $this->parser->parse('not-matching', $config);
        $this->assertNull($result['owner']);
        $this->assertNull($result['sku']);
        $this->assertNull($result['location']);
    }

    public function test_invalid_regex_handled_gracefully(): void
    {
        $config = [
            'enabled' => true,
            'mode' => 'regex',
            'regex' => [
                'pattern' => '/invalid[regex/',
            ],
        ];

        $result = $this->parser->parse('test', $config);
        $this->assertNull($result['owner']);
        $this->assertNull($result['sku']);
    }

    public function test_validate_returns_errors_for_missing_mode(): void
    {
        $errors = $this->parser->validate([]);
        $this->assertNotEmpty($errors);
    }

    public function test_validate_returns_errors_for_empty_regex(): void
    {
        $errors = $this->parser->validate(['mode' => 'regex', 'regex' => ['pattern' => '']]);
        $this->assertNotEmpty($errors);
    }

    public function test_validate_returns_errors_for_invalid_regex(): void
    {
        $errors = $this->parser->validate(['mode' => 'regex', 'regex' => ['pattern' => '/bad[/']]);
        $this->assertNotEmpty($errors);
    }

    public function test_validate_valid_config_returns_no_errors(): void
    {
        $errors = $this->parser->validate([
            'mode' => 'template',
            'template' => [
                'type' => 'delimiter',
                'delimiter' => ' ',
                'segments' => [['field' => 'sku', 'position' => 0]],
            ],
        ]);
        $this->assertEmpty($errors);
    }

    public function test_test_method_returns_success(): void
    {
        $result = $this->parser->test('JONES SKU001 SHELF', [
            'mode' => 'template',
            'template' => [
                'type' => 'delimiter',
                'delimiter' => ' ',
                'segments' => [
                    ['field' => 'owner', 'position' => 0],
                    ['field' => 'sku', 'position' => 1],
                    ['field' => 'location', 'position' => 2],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('JONES', $result['result']['owner']);
        $this->assertNull($result['error']);
    }

    public function test_test_method_returns_error_for_invalid_config(): void
    {
        $result = $this->parser->test('test', ['mode' => 'regex', 'regex' => ['pattern' => '']]);
        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
    }

    private function arcConfig(): array
    {
        return [
            'enabled' => true,
            'mode' => 'regex',
            'regex' => [
                'pattern' => '/^(?P<owner>ARC)\s+(?:[A-Z]{2,3}\s+)?(?P<sku>[A-Z][A-Z0-9]*-[A-Z0-9][\w-]*(?:-[A-Z])?)(?:\s*\(x\d+\))?(?:\s+(?P<location>.+))?$/',
            ],
        ];
    }

    public function test_arc_sku_with_initials_and_long_location(): void
    {
        $result = $this->parser->parse('ARC IND-18979-9 SHELF CM RM 133 TUB DUDEBRO', $this->arcConfig());
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('IND-18979-9', $result['sku']);
        $this->assertEquals('SHELF CM RM 133 TUB DUDEBRO', $result['location']);
    }

    public function test_arc_sku_with_abc_initials(): void
    {
        $result = $this->parser->parse('ARC ABC PO-1033-1022-312 FOH C5', $this->arcConfig());
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('PO-1033-1022-312', $result['sku']);
        $this->assertEquals('FOH C5', $result['location']);
    }

    public function test_arc_sku_with_quantity_multiplier(): void
    {
        $result = $this->parser->parse('ARC ABC CS-1043-1020-102 (x12) C3 / BOOST', $this->arcConfig());
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('CS-1043-1020-102', $result['sku']);
        $this->assertEquals('C3 / BOOST', $result['location']);
    }

    public function test_arc_sku_with_letter_suffix(): void
    {
        $result = $this->parser->parse('ARC ABC PO-1033-1022-309-A FOH R3 CART', $this->arcConfig());
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('PO-1033-1022-309-A', $result['sku']);
        $this->assertEquals('FOH R3 CART', $result['location']);
    }

    public function test_arc_sku_with_po_initials(): void
    {
        $result = $this->parser->parse('ARC PO PO-1033-1032-171 FOH C2 RACK TUB CHUDMEN', $this->arcConfig());
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('PO-1033-1032-171', $result['sku']);
        $this->assertEquals('FOH C2 RACK TUB CHUDMEN', $result['location']);
    }

    public function test_arc_sku_no_initials_elec(): void
    {
        $result = $this->parser->parse('ARC ELEC-71905-532 LL1', $this->arcConfig());
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('ELEC-71905-532', $result['sku']);
        $this->assertEquals('LL1', $result['location']);
    }

    public function test_arc_sku_no_initials_with_commas_in_location(): void
    {
        $result = $this->parser->parse('ARC PO-1033-1031-104 Shelf ET 1,2,3', $this->arcConfig());
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('PO-1033-1031-104', $result['sku']);
        $this->assertEquals('Shelf ET 1,2,3', $result['location']);
    }

    public function test_arc_sku_without_location(): void
    {
        $result = $this->parser->parse('ARC IND-60474-138', $this->arcConfig());
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('IND-60474-138', $result['sku']);
        $this->assertNull($result['location']);
    }

    public function test_arc_sku_without_location_letter_suffix(): void
    {
        $result = $this->parser->parse('ARC PO-1033-1056-120-A', $this->arcConfig());
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('PO-1033-1056-120-A', $result['sku']);
        $this->assertNull($result['location']);
    }

    public function test_arc_sku_with_alphanumeric_prefix(): void
    {
        $result = $this->parser->parse('ARC DJ G73JH-X1 Shelf 3B', $this->arcConfig());
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('G73JH-X1', $result['sku']);
        $this->assertEquals('Shelf 3B', $result['location']);
    }

    public function test_arc_regex_non_amp_sku_returns_nulls(): void
    {
        $result = $this->parser->parse('JONES SKU001 SHELF-A', $this->arcConfig());
        $this->assertNull($result['owner']);
        $this->assertNull($result['sku']);
        $this->assertNull($result['location']);
    }

    // --- Multi-parser tests ---

    private function ownerLocationConfig(): array
    {
        return [
            'enabled' => true,
            'prefix' => '',
            'mode' => 'regex',
            'regex' => [
                'pattern' => '/^(?P<owner>[A-Z][A-Z0-9]{1,5})\s+(?P<location>.+)$/',
            ],
        ];
    }

    public function test_owner_location_parser_hawk(): void
    {
        $result = $this->parser->parse('HAWK RM 132 SHELF M GROUND LVL', $this->ownerLocationConfig());
        $this->assertEquals('HAWK', $result['owner']);
        $this->assertNull($result['sku']);
        $this->assertEquals('RM 132 SHELF M GROUND LVL', $result['location']);
    }

    public function test_owner_location_parser_pine(): void
    {
        $result = $this->parser->parse('PINE RM 134 SHELF BO TOP TUB TENET', $this->ownerLocationConfig());
        $this->assertEquals('PINE', $result['owner']);
        $this->assertNull($result['sku']);
        $this->assertEquals('RM 134 SHELF BO TOP TUB TENET', $result['location']);
    }

    public function test_owner_location_parser_prefix_with_digit(): void
    {
        $result = $this->parser->parse('REEF6 ABC 90508 MANCHURIANBROCCOLI EB', $this->ownerLocationConfig());
        $this->assertEquals('REEF6', $result['owner']);
        $this->assertNull($result['sku']);
        $this->assertEquals('ABC 90508 MANCHURIANBROCCOLI EB', $result['location']);
    }

    public function test_parse_multi_matches_first_parser(): void
    {
        $parsers = [$this->arcConfig(), $this->ownerLocationConfig()];
        $result = $this->parser->parseMulti('ARC ABC PO-1033-1022-312 FOH C5', $parsers);
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('PO-1033-1022-312', $result['sku']);
    }

    public function test_parse_multi_falls_through_to_second_parser(): void
    {
        $arcWithPrefix = array_merge($this->arcConfig(), ['prefix' => 'ARC']);
        $parsers = [$arcWithPrefix, $this->ownerLocationConfig()];
        $result = $this->parser->parseMulti('HAWK RM 132 SHELF M GROUND LVL', $parsers);
        $this->assertEquals('HAWK', $result['owner']);
        $this->assertEquals('RM 132 SHELF M GROUND LVL', $result['location']);
    }

    public function test_parse_multi_skips_disabled_parsers(): void
    {
        $disabled = array_merge($this->arcConfig(), ['enabled' => false, 'prefix' => 'ARC']);
        $ownerLoc = array_merge($this->ownerLocationConfig(), ['prefix' => 'HAWK']);
        $parsers = [$disabled, $ownerLoc];
        $result = $this->parser->parseMulti('ARC ABC PO-1033-1022-312 FOH C5', $parsers);
        // ARC parser disabled, HAWK prefix doesn't match -> null
        $this->assertNull($result);
    }

    public function test_parse_multi_returns_null_when_no_match(): void
    {
        $arcWithPrefix = array_merge($this->arcConfig(), ['prefix' => 'ARC']);
        $bstarOnly = array_merge($this->ownerLocationConfig(), ['prefix' => 'HAWK']);
        $parsers = [$arcWithPrefix, $bstarOnly];
        $result = $this->parser->parseMulti('UNKNOWN 12345', $parsers);
        $this->assertNull($result);
    }

    public function test_parse_multi_empty_string_returns_null(): void
    {
        $result = $this->parser->parseMulti('', [$this->arcConfig()]);
        $this->assertNull($result);
    }

    public function test_normalize_parsers_from_new_format(): void
    {
        $config = ['sku_parsers' => [$this->arcConfig(), $this->ownerLocationConfig()]];
        $result = SkuParserService::normalizeParsers($config);
        $this->assertCount(2, $result);
    }

    public function test_normalize_parsers_from_old_format(): void
    {
        $config = ['sku_parser' => $this->arcConfig()];
        $result = SkuParserService::normalizeParsers($config);
        $this->assertCount(1, $result);
        $this->assertEquals('ARC', $result[0]['regex']['pattern'] ? 'ARC' : '');
    }

    public function test_normalize_parsers_empty_config(): void
    {
        $result = SkuParserService::normalizeParsers([]);
        $this->assertCount(0, $result);
    }

    public function test_normalize_parsers_new_format_takes_precedence(): void
    {
        $config = [
            'sku_parser' => $this->arcConfig(),
            'sku_parsers' => [$this->arcConfig(), $this->ownerLocationConfig()],
        ];
        $result = SkuParserService::normalizeParsers($config);
        $this->assertCount(2, $result);
    }
}
