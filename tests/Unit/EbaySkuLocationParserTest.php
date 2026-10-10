<?php

namespace Tests\Unit;

use App\Services\EbaySkuLocationParser;
use PHPUnit\Framework\TestCase;

class EbaySkuLocationParserTest extends TestCase
{
    protected EbaySkuLocationParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new EbaySkuLocationParser;
    }

    // --- Empty / edge cases ---

    public function test_empty_string_returns_all_nulls(): void
    {
        $result = $this->parser->parse('');
        $this->assertNull($result['owner']);
        $this->assertNull($result['room']);
        $this->assertNull($result['shelf']);
    }

    // --- Owner extraction ---

    public function test_extracts_owner_from_first_word(): void
    {
        $result = $this->parser->parse('HAWK RM 132 SHELF 1');
        $this->assertEquals('HAWK', $result['owner']);
    }

    public function test_extracts_owner_with_trailing_digit(): void
    {
        $result = $this->parser->parse('REEF6 ABC 90508 MANCHURIANBROCCOLI EB');
        $this->assertEquals('REEF6', $result['owner']);
    }

    // --- Room extraction ---

    public function test_extracts_room_with_space(): void
    {
        $result = $this->parser->parse('HAWK RM 132 SHELF 1');
        $this->assertEquals('132', $result['room']);
    }

    public function test_extracts_room_without_space(): void
    {
        $result = $this->parser->parse('PINE RM132 SHELF 1');
        $this->assertEquals('132', $result['room']);
    }

    public function test_extracts_room_lowercase(): void
    {
        $result = $this->parser->parse('ARC IND-39545-1947 Rm119 Shelf AT-2');
        $this->assertEquals('119', $result['room']);
    }

    public function test_extracts_room_134(): void
    {
        $result = $this->parser->parse('PINE RM 134 SHELF BO TOP TUB TENET');
        $this->assertEquals('134', $result['room']);
    }

    // --- Shelf extraction ---

    public function test_extracts_shelf_numeric(): void
    {
        $result = $this->parser->parse('HAWK RM 132 SHELF 44 TUB LABRADOR');
        $this->assertEquals('44', $result['shelf']);
    }

    public function test_extracts_shelf_alpha(): void
    {
        $result = $this->parser->parse('PINE RM 132 SHELF M LVL 5');
        $this->assertEquals('M', $result['shelf']);
    }

    public function test_extracts_shelf_alphanumeric(): void
    {
        $result = $this->parser->parse('OAKS RM 132 SHELF BF4');
        $this->assertEquals('BF4', $result['shelf']);
    }

    public function test_extracts_shelf_with_dash(): void
    {
        $result = $this->parser->parse('ARC IND-39545-1947 Rm119 Shelf AT-2');
        $this->assertEquals('AT-2', $result['shelf']);
    }

    public function test_extracts_shlf_typo(): void
    {
        $result = $this->parser->parse('OAKS COOP SHLF 54 RM 132');
        $this->assertEquals('54', $result['shelf']);
        $this->assertEquals('132', $result['room']);
    }

    // --- Tub extraction ---

    public function test_extracts_tub(): void
    {
        $result = $this->parser->parse('HAWK RM 132 SHELF 1 TUB ZUCCBUCKS');
        $this->assertEquals('tub', $result['container_type']);
        $this->assertEquals('ZUCCBUCKS', $result['container_name']);
    }

    public function test_extracts_tub_lowercase(): void
    {
        $result = $this->parser->parse('DUNE ABC 65523 Tub GreenieMeanie Shelf EB');
        $this->assertEquals('tub', $result['container_type']);
        $this->assertEquals('GREENIEMEANIE', $result['container_name']);
    }

    public function test_extracts_tub_with_special_name(): void
    {
        $result = $this->parser->parse('ARC IND-20351-688 BRC-2 Tub $5FOOTLONG AL-167');
        $this->assertEquals('tub', $result['container_type']);
        $this->assertEquals('$5FOOTLONG', $result['container_name']);
    }

    // --- Box extraction ---

    public function test_extracts_box(): void
    {
        $result = $this->parser->parse('OAKS COOP BOX TRAPMAXXING MT1 SHLF 54 RM 132');
        $this->assertEquals('box', $result['container_type']);
        $this->assertEquals('TRAPMAXXING', $result['container_name']);
    }

    // --- Level extraction ---

    public function test_extracts_level(): void
    {
        $result = $this->parser->parse('PINE SHELF 28 LVL 4');
        $this->assertEquals('4', $result['level']);
    }

    public function test_extracts_level_no_space(): void
    {
        $result = $this->parser->parse('ARC IND-62659-14 RM1322 Shelf CM LVL3');
        $this->assertEquals('3', $result['level']);
    }

    public function test_extracts_ground_level(): void
    {
        $result = $this->parser->parse('HAWK RM 132 SHELF M GROUND LVL');
        $this->assertEquals('GROUND', $result['level']);
    }

    // --- Special areas ---

    public function test_extracts_foh(): void
    {
        $result = $this->parser->parse('ARC ABC PO-1033-1022-312 FOH C5');
        $this->assertEquals('FOH', $result['special_area']);
    }

    public function test_extracts_mezz(): void
    {
        $result = $this->parser->parse('COOP MADM 52361 Mezzanine');
        $this->assertEquals('MEZZ', $result['special_area']);
    }

    // --- Consignor extraction ---

    public function test_extracts_consignor_abc(): void
    {
        $result = $this->parser->parse('DUNE ABC 65523 Tub GreenieMeanie Shelf EB');
        $this->assertEquals('ABC', $result['consignor']);
        $this->assertEquals('65523', $result['item_id']);
    }

    public function test_extracts_consignor_coop(): void
    {
        $result = $this->parser->parse('FERN COOP 89212 BM TOP RIGHTRM 134');
        $this->assertEquals('COOP', $result['consignor']);
        $this->assertEquals('89212', $result['item_id']);
    }

    public function test_extracts_consignor_ghi(): void
    {
        $result = $this->parser->parse('OAKS GHI box TRADWIFE rm211mezz. 76321');
        $this->assertEquals('GHI', $result['consignor']);
    }

    // --- ARC pattern ---

    public function test_arc_extracts_product_sku(): void
    {
        $result = $this->parser->parse('ARC IND-18979-9 SHELF CM RM 133 TUB DUDEBRO');
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('IND-18979-9', $result['product_sku']);
        $this->assertEquals('133', $result['room']);
        $this->assertEquals('CM', $result['shelf']);
        $this->assertEquals('tub', $result['container_type']);
        $this->assertEquals('DUDEBRO', $result['container_name']);
    }

    public function test_arc_with_consignor_and_product_sku(): void
    {
        $result = $this->parser->parse('ARC ABC PO-1033-1022-312 FOH C5');
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('ABC', $result['consignor']);
        $this->assertEquals('PO-1033-1022-312', $result['product_sku']);
        $this->assertEquals('FOH', $result['special_area']);
    }

    public function test_arc_with_room_shelf_tub(): void
    {
        $result = $this->parser->parse('ARC IND-83611-781 RM132 Shelf 1 Tub Loons');
        $this->assertEquals('IND-83611-781', $result['product_sku']);
        $this->assertEquals('132', $result['room']);
        $this->assertEquals('1', $result['shelf']);
        $this->assertEquals('tub', $result['container_type']);
        $this->assertEquals('LOONS', $result['container_name']);
    }

    // --- Full non-ARC parsing ---

    public function test_full_parse_hawk(): void
    {
        $result = $this->parser->parse('HAWK RM 132 SHELF 44 TUB LABRADOR');
        $this->assertEquals('HAWK', $result['owner']);
        $this->assertNull($result['consignor']);
        $this->assertEquals('132', $result['room']);
        $this->assertEquals('44', $result['shelf']);
        $this->assertEquals('tub', $result['container_type']);
        $this->assertEquals('LABRADOR', $result['container_name']);
    }

    public function test_full_parse_pine_with_level(): void
    {
        $result = $this->parser->parse('PINE RM 132 SHELF M LVL 5');
        $this->assertEquals('PINE', $result['owner']);
        $this->assertEquals('132', $result['room']);
        $this->assertEquals('M', $result['shelf']);
        $this->assertEquals('5', $result['level']);
    }

    public function test_no_room_returns_null_room(): void
    {
        $result = $this->parser->parse('PINE SHELF 28 LVL 4');
        $this->assertEquals('PINE', $result['owner']);
        $this->assertNull($result['room']);
        $this->assertEquals('28', $result['shelf']);
        $this->assertEquals('4', $result['level']);
    }

    public function test_keywords_in_reversed_order(): void
    {
        $result = $this->parser->parse('PINE TUB BURGERKING SHELF 54 RM 132');
        $this->assertEquals('PINE', $result['owner']);
        $this->assertEquals('132', $result['room']);
        $this->assertEquals('54', $result['shelf']);
        $this->assertEquals('tub', $result['container_type']);
        $this->assertEquals('BURGERKING', $result['container_name']);
    }

    public function test_jbec_with_consignor_box_shelf_room(): void
    {
        $result = $this->parser->parse('OAKS COOP BOX TRAPMAXXING MT1 SHLF 54 RM 132');
        $this->assertEquals('OAKS', $result['owner']);
        $this->assertEquals('COOP', $result['consignor']);
        $this->assertEquals('box', $result['container_type']);
        $this->assertEquals('TRAPMAXXING', $result['container_name']);
        $this->assertEquals('54', $result['shelf']);
        $this->assertEquals('132', $result['room']);
    }

    public function test_rain_with_consignor_item_id_tub_shelf(): void
    {
        $result = $this->parser->parse('DUNE ABC 65523 Tub GreenieMeanie Shelf EB');
        $this->assertEquals('DUNE', $result['owner']);
        $this->assertEquals('ABC', $result['consignor']);
        $this->assertEquals('65523', $result['item_id']);
        $this->assertEquals('tub', $result['container_type']);
        $this->assertEquals('GREENIEMEANIE', $result['container_name']);
        $this->assertEquals('EB', $result['shelf']);
    }

    public function test_sku_with_no_location_keywords(): void
    {
        $result = $this->parser->parse('MESA ABC 62814 THEGRID');
        $this->assertEquals('MESA', $result['owner']);
        $this->assertEquals('ABC', $result['consignor']);
        $this->assertEquals('62814', $result['item_id']);
        $this->assertNull($result['room']);
        $this->assertNull($result['shelf']);
        $this->assertNull($result['container_type']);
    }

    // --- Leading bracket prefix stripping ---

    public function test_strips_leading_single_letter_bracket_prefix(): void
    {
        $result = $this->parser->parse('(C) HAWK Panthers Shelf B3');
        $this->assertEquals('HAWK', $result['owner']);
        $this->assertEquals('B3', $result['shelf']);
    }

    public function test_strips_leading_bracket_with_dash_separator(): void
    {
        $result = $this->parser->parse('(D) - MESA SHELF 30 TUB STITCHES');
        $this->assertEquals('MESA', $result['owner']);
        $this->assertEquals('30', $result['shelf']);
        $this->assertEquals('STITCHES', $result['container_name']);
    }

    public function test_strips_three_letter_bracket_prefix(): void
    {
        $result = $this->parser->parse('(COOP) PINE RM 134 SHELF B3');
        $this->assertEquals('PINE', $result['owner']);
        $this->assertEquals('134', $result['room']);
        $this->assertEquals('B3', $result['shelf']);
    }

    // --- ARC PENDING handling ---

    public function test_arc_id_pending_tags_intake_status(): void
    {
        $result = $this->parser->parse('ARC ID PENDING RM 132 Shelf 7');
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('pending', $result['intake_status']);
        $this->assertNull($result['consignor'], 'ID should NOT be tagged as a consignor.');
        $this->assertNull($result['product_sku']);
        $this->assertEquals('132', $result['room']);
        $this->assertEquals('7', $result['shelf']);
    }

    public function test_arc_po_pending_with_placeholder_tags_intake_status(): void
    {
        $result = $this->parser->parse('ARC PO PENDING 7776MC12 RM 132 Shelf 2 LVL 2');
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('pending', $result['intake_status']);
        $this->assertNull($result['consignor']);
        $this->assertEquals('132', $result['room']);
        $this->assertEquals('2', $result['shelf']);
        $this->assertEquals('2', $result['level']);
    }

    public function test_arc_po_pending_loc_pending_extracts_nothing_else(): void
    {
        $result = $this->parser->parse('ARC PO PENDING LOC PENDING');
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('pending', $result['intake_status']);
        $this->assertNull($result['consignor']);
        $this->assertNull($result['room']);
    }

    // --- Known-owners exclude from consignor ---

    public function test_known_owner_token_is_not_tagged_as_consignor(): void
    {
        $parser = new EbaySkuLocationParser(['HAWK', 'FERN', 'PINE']);
        $result = $parser->parse('PINE HAWK SHELF B3');
        $this->assertEquals('PINE', $result['owner']);
        $this->assertNull($result['consignor'], 'HAWK is in known-owners list and must not be tagged as a consignor.');
        $this->assertEquals('B3', $result['shelf']);
    }

    public function test_unknown_token_still_tagged_as_consignor_in_amp_path(): void
    {
        $parser = new EbaySkuLocationParser(['HAWK']);
        $result = $parser->parse('ARC ABC IND-99 SHELF 7');
        $this->assertEquals('ABC', $result['consignor'], 'ABC is not in known-owners; ARC path should still tag it.');
    }

    // --- Period-suffix item ID stripping ---

    public function test_period_suffix_item_id_does_not_block_shelf(): void
    {
        $result = $this->parser->parse('Shelf B3.19586');
        $this->assertEquals('B3', $result['shelf']);
    }

    public function test_period_suffix_does_not_eat_decimal_shelf_number(): void
    {
        // "33.15731" looks like a decimal but it's a shelf number with an
        // item-id suffix. We should still capture shelf=33.
        $result = $this->parser->parse('HAWK Shelf 33.15731');
        $this->assertEquals('HAWK', $result['owner']);
        $this->assertEquals('33', $result['shelf']);
    }

    // --- OFFICE special area ---

    public function test_office_is_recognized_special_area(): void
    {
        $result = $this->parser->parse('HAWK OFFICE TUB STAPLES');
        $this->assertEquals('HAWK', $result['owner']);
        $this->assertEquals('OFFICE', $result['special_area']);
    }

    // --- KELP $price ordering still works ---

    public function test_kelp_dollar_price_then_consignor_still_works(): void
    {
        $result = $this->parser->parse('KELP $3.44 COOP 12345 SHELF 5');
        $this->assertEquals('KELP', $result['owner']);
        $this->assertEquals('COOP', $result['consignor']);
        $this->assertEquals('12345', $result['item_id']);
        $this->assertEquals('5', $result['shelf']);
    }

    // --- bracket_prefix capture (Gap #3) ---

    public function test_captures_single_letter_bracket_prefix(): void
    {
        $result = $this->parser->parse('(C) HAWK RM 134 SHELF B3');
        $this->assertEquals('C', $result['bracket_prefix']);
        $this->assertEquals('HAWK', $result['owner']);
        $this->assertEquals('134', $result['room']);
    }

    public function test_captures_three_letter_bracket_prefix(): void
    {
        $result = $this->parser->parse('(GHI) KELP $127.52 SHELF B3');
        $this->assertEquals('GHI', $result['bracket_prefix']);
        $this->assertEquals('KELP', $result['owner']);
    }

    public function test_captures_bracket_prefix_with_dash_separator(): void
    {
        $result = $this->parser->parse('(C)- PINE Tub Morrigan Shelf B3');
        $this->assertEquals('C', $result['bracket_prefix']);
        $this->assertEquals('PINE', $result['owner']);
        $this->assertEquals('MORRIGAN', $result['container_name']);
    }

    public function test_bracket_prefix_uppercased(): void
    {
        $result = $this->parser->parse('(c) HAWK SHELF 1');
        $this->assertEquals('C', $result['bracket_prefix']);
    }

    public function test_no_bracket_prefix_returns_null(): void
    {
        $result = $this->parser->parse('HAWK SHELF 1');
        $this->assertNull($result['bracket_prefix']);
    }

    // --- Case-insensitive owner (Gap #6) ---

    public function test_lowercase_owner_is_matched_and_uppercased(): void
    {
        $result = $this->parser->parse('arc PO-1043-1020-101 FOH');
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('PO-1043-1020-101', $result['product_sku']);
        $this->assertEquals('FOH', $result['special_area']);
    }

    public function test_mixed_case_owner_is_uppercased(): void
    {
        $result = $this->parser->parse('Hawk RM 134 SHELF 1');
        $this->assertEquals('HAWK', $result['owner']);
        $this->assertEquals('134', $result['room']);
    }

    // --- lister_initials vs consignor (Gap #2) ---

    public function test_known_lister_routes_to_lister_initials_not_consignor(): void
    {
        $parser = new EbaySkuLocationParser([], ['ABC', 'DEF', 'GHI']);
        $result = $parser->parse('HAWK ABC SHELF 28 LVL 3');
        $this->assertEquals('ABC', $result['lister_initials']);
        $this->assertNull($result['consignor']);
        $this->assertEquals('HAWK', $result['owner']);
        $this->assertEquals('28', $result['shelf']);
        $this->assertEquals('3', $result['level']);
    }

    public function test_known_lister_in_amp_pattern(): void
    {
        $parser = new EbaySkuLocationParser([], ['ABC']);
        $result = $parser->parse('ARC ABC PO-1043-1020-101 FOH');
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('ABC', $result['lister_initials']);
        $this->assertNull($result['consignor']);
        $this->assertEquals('PO-1043-1020-101', $result['product_sku']);
    }

    public function test_unknown_lister_token_still_falls_back_to_consignor(): void
    {
        $parser = new EbaySkuLocationParser([], ['ABC']);
        $result = $parser->parse('HAWK COOP 58585 SHELF 29 TUB ELTONJOHN');
        $this->assertNull($result['lister_initials']);
        $this->assertEquals('COOP', $result['consignor']);
        $this->assertEquals('58585', $result['item_id']);
    }

    public function test_without_known_listers_config_abc_stays_as_consignor(): void
    {
        $result = $this->parser->parse('HAWK ABC SHELF 28');
        $this->assertNull($result['lister_initials']);
        $this->assertEquals('ABC', $result['consignor']);
    }

    public function test_kelp_price_then_lister_routes_to_lister_initials(): void
    {
        $parser = new EbaySkuLocationParser([], ['ABC']);
        $result = $parser->parse('KELP $3.44 ABC SHELF 5');
        $this->assertEquals('KELP', $result['owner']);
        $this->assertEquals('ABC', $result['lister_initials']);
        $this->assertNull($result['consignor']);
    }

    public function test_lister_initials_returns_null_by_default(): void
    {
        $result = $this->parser->parse('HAWK RM 132 SHELF 1');
        $this->assertNull($result['lister_initials']);
    }
}
