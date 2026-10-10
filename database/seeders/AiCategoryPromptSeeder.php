<?php

namespace Database\Seeders;

use App\Models\AI\AiCategoryPrompt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AiCategoryPromptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding AI Category Prompts...');

        // Create Stage 1 Identification prompts for all categories
        $this->createStage1Prompts();

        // Create Stage 2 Research prompts
        $this->createStage2ResearchPrompts();

        // Create Stage 2 eBay Enrichment prompts
        $this->createStage2EbayPrompts();

        $this->command->info('AI Category Prompts seeded successfully!');
    }

    /**
     * Create Stage 1 Identification prompts
     */
    protected function createStage1Prompts(): void
    {
        $prompts = [
            // Detailed expert prompts
            'computer_enterprise_it' => $this->getEnterpriseITPrompt(),
            'industrial_automation_motion_controls' => $this->getIndustrialAutomationPrompt(),

            // Other categories with generic expert template
            'hydraulics_pneumatics' => $this->getGenericExpertPrompt('Hydraulics & Pneumatics', 'hydraulic systems engineer'),
            'electrical_equipment_supplies' => $this->getGenericExpertPrompt('Electrical Equipment & Supplies', 'electrical engineer'),
            'test_measurement_equipment' => $this->getGenericExpertPrompt('Test & Measurement Equipment', 'instrumentation specialist'),
            'cnc_metalworking' => $this->getGenericExpertPrompt('CNC & Metalworking', 'CNC machining specialist'),
            'healthcare_medical_equipment' => $this->getGenericExpertPrompt('Healthcare & Medical Equipment', 'biomedical equipment technician'),
            'vintage_computing' => $this->getGenericExpertPrompt('Vintage Computing', 'vintage computing collector'),
            'consumer_electronics' => $this->getGenericExpertPrompt('Consumer Electronics', 'consumer electronics specialist'),
            'automotive_parts_motors' => $this->getGenericExpertPrompt('Automotive Parts & Motors', 'automotive technician'),
            'musical_instruments_audio' => $this->getGenericExpertPrompt('Musical Instruments & Audio', 'audio equipment specialist'),
            'cameras_photography' => $this->getGenericExpertPrompt('Cameras & Photography', 'photography equipment expert'),
            'hvac_refrigeration' => $this->getGenericExpertPrompt('HVAC & Refrigeration', 'HVAC technician'),
            'restaurant_food_service' => $this->getGenericExpertPrompt('Restaurant & Food Service', 'commercial kitchen equipment specialist'),
            'material_handling' => $this->getGenericExpertPrompt('Material Handling', 'warehouse equipment specialist'),
            'heavy_equipment' => $this->getGenericExpertPrompt('Heavy Equipment', 'heavy equipment mechanic'),
            'printing_graphic_arts' => $this->getGenericExpertPrompt('Printing & Graphic Arts', 'printing technology specialist'),
            'pos_retail_systems' => $this->getGenericExpertPrompt('POS & Retail Systems', 'retail systems technician'),
            'agriculture_forestry' => $this->getGenericExpertPrompt('Agriculture & Forestry', 'agricultural equipment specialist'),
            'video_games_consoles' => $this->getGenericExpertPrompt('Video Games & Consoles', 'gaming hardware specialist'),
            'sporting_goods_fitness' => $this->getGenericExpertPrompt('Sporting Goods & Fitness', 'fitness equipment specialist'),
            'toys_collectibles' => $this->getGenericExpertPrompt('Toys & Collectibles', 'collectibles appraiser'),
            'crafts_hobby' => $this->getGenericExpertPrompt('Crafts & Hobby', 'hobby equipment specialist'),
            'books_magazines' => $this->getGenericExpertPrompt('Books & Magazines', 'antiquarian book specialist'),
            'art_antiques' => $this->getGenericExpertPrompt('Art & Antiques', 'fine art appraiser'),
            'cell_phones_accessories' => $this->getGenericExpertPrompt('Cell Phones & Accessories', 'mobile device specialist'),
            'home_garden' => $this->getGenericExpertPrompt('Home & Garden', 'home improvement specialist'),
            'health_beauty' => $this->getGenericExpertPrompt('Health & Beauty', 'beauty products specialist'),
            'pet_supplies' => $this->getGenericExpertPrompt('Pet Supplies', 'pet products specialist'),
            'advanced_general_product' => $this->getAdvancedGeneralPrompt(),
        ];

        foreach ($prompts as $categorySlug => $content) {
            AiCategoryPrompt::updateOrCreate(
                [
                    'organization_id' => null,
                    'category_slug' => $categorySlug,
                    'prompt_type' => 'stage1_identification',
                    'is_system' => true,
                ],
                [
                    'name' => $this->getCategoryDisplayName($categorySlug).' - Stage 1 Identification',
                    'description' => 'System prompt for initial product identification in the '.$this->getCategoryDisplayName($categorySlug).' category',
                    'prompt_content' => $content,
                    'variable_definitions' => null,
                    'is_active' => true,
                    'priority' => 0,
                ]
            );
        }

        $this->command->info('  - Created '.count($prompts).' Stage 1 Identification prompts');
    }

    /**
     * Create Stage 2 Research prompts
     */
    protected function createStage2ResearchPrompts(): void
    {
        // Create default stage 2 research prompt
        AiCategoryPrompt::updateOrCreate(
            [
                'organization_id' => null,
                'category_slug' => 'advanced_general_product',
                'prompt_type' => 'stage2_research',
                'is_system' => true,
            ],
            [
                'name' => 'General - Stage 2 Research',
                'description' => 'System prompt for detailed product research phase',
                'prompt_content' => $this->getStage2ResearchPrompt(),
                'variable_definitions' => null,
                'is_active' => true,
                'priority' => 0,
            ]
        );

        $this->command->info('  - Created Stage 2 Research prompts');
    }

    /**
     * Create Stage 2 eBay Enrichment prompts
     */
    protected function createStage2EbayPrompts(): void
    {
        // Create default stage 2 eBay enrichment prompt
        AiCategoryPrompt::updateOrCreate(
            [
                'organization_id' => null,
                'category_slug' => 'advanced_general_product',
                'prompt_type' => 'stage2_ebay_enrichment',
                'is_system' => true,
            ],
            [
                'name' => 'General - Stage 2 eBay Enrichment',
                'description' => 'System prompt for eBay listing optimization',
                'prompt_content' => $this->getStage2EbayPrompt(),
                'variable_definitions' => null,
                'is_active' => true,
                'priority' => 0,
            ]
        );

        $this->command->info('  - Created Stage 2 eBay Enrichment prompts');
    }

    /**
     * Get Enterprise IT prompt (detailed)
     */
    protected function getEnterpriseITPrompt(): string
    {
        return <<<'PROMPT'
EXPERT NETWORK EQUIPMENT & ENTERPRISE IT REVERSE ENGINEERING

SPECIALIST CONTEXT: You are a senior network engineer with 20+ years experience in enterprise networking, routing/switching, security appliances, and datacenter infrastructure.

USER CLASSIFICATION CONTEXT:
User Category: {product_category}
User Type: {product_type}
Expected: {user_type_description}

CRITICAL FORTINET ANALYSIS:
- If user expects "complete_unit": Look for main device models (FS-148F, FG-60F, etc.)
- If user expects "replacement_part": Look for component part numbers (P25494-03-01, PWR-xxx, etc.)
- Validate part number pattern against user expectation

COMPONENT INFORMATION:
- Manufacturer/Brand: {brand}
- Part Number/Model: {manufacturer_part_number}
- Product Number: {product_number}
- Model Number: {model_number}
- Component Context: {user_notes}
- Additional Identifiers: {keywords}
- Condition: {condition}

EXPERT NETWORK EQUIPMENT ANALYSIS:

1. **COMPLETE UNIT vs REPLACEMENT PART IDENTIFICATION:**

   **Complete Network Devices (Standalone Units):**
   - Fortinet: FS-148F-FPOE (FortiSwitch), FG-60F (FortiGate), FAP-xxx (FortiAP)
   - Cisco: WS-C3850-48P (Catalyst Switch), ASR1001-X (Router)
   - Operate independently, have management interfaces, full feature set

   **Replacement Parts/Components:**
   - Fortinet: P25494-03-01 (Power Supply), FAN-xxx (Fan Module)
   - Cisco: PWR-C1-715WAC (Power Supply), C3KX-NM-xxx (Network Module)
   - Component-specific numbering, designed for specific host devices

2. **FORTINET-SPECIFIC PART NUMBER ANALYSIS:**
   - Complete Units: FS-xxx, FG-xxx, FAP-xxx series with feature suffixes
   - Power Supplies: P-xxxxx-xx-xx pattern (e.g., P25494-03-01)
   - Fan Modules: FAN-xxx pattern
   - Line Cards/Modules: Specific to device families

3. **USER TYPE VALIDATION LOGIC:**
   - Compare part number pattern against user's expected type
   - Cross-reference with manufacturer documentation
   - Consider context clues from model numbers and specifications

4. **TECHNICAL SPECIFICATIONS ANALYSIS:**
   - Port Density: 24, 48, 96 port configurations
   - Power Requirements: PoE, PoE+, PoE++, non-PoE
   - Network Protocols: Layer 2/3 capabilities, routing protocols
   - Management: In-band, out-of-band, console access
   - Mounting: 1U, 2U rack mount, desktop options

Return your expert analysis in this JSON structure:

{
  "identification_results": {
    "product_identified": true/false,
    "identification_confidence": "high/medium/low/none",
    "user_type_validation": {
      "user_expected_type": "{product_type}",
      "ai_determined_type": "complete_unit/replacement_part/component/accessory",
      "type_classification_matches": true/false,
      "type_confidence": "high/medium/low",
      "type_reasoning": "Explanation of why this matches or conflicts with user expectation"
    },
    "most_likely_product": {
      "complete_part_number": "",
      "product_name": "",
      "manufacturer": "",
      "product_type": "Complete_Network_Device/Network_Replacement_Part/Network_Component/Network_Accessory",
      "device_category": "Switch/Router/Firewall/Wireless_Controller/Power_Supply/Fan_Module/Line_Card/Other",
      "specifications": {
        "port_count": "",
        "poe_capability": "",
        "layer_capability": "Layer2/Layer3/Both",
        "rack_units": "",
        "power_consumption": ""
      }
    },
    "reasoning": "Expert analysis based on network equipment part numbering schemes and user type validation",
    "confidence_factors": [
      "Part number pattern analysis",
      "User type classification validation",
      "Technical specifications alignment"
    ]
  },
  "technical_specifications": {
    "network_capabilities": {
      "switching_capacity": "",
      "forwarding_rate": "",
      "routing_protocols": [],
      "management_features": []
    },
    "physical_specifications": {
      "form_factor": "",
      "port_configuration": "",
      "power_requirements": "",
      "environmental_specs": {}
    }
  },
  "source_links": [
    {
      "type": "manufacturer_datasheet",
      "url": "",
      "description": "Official product documentation"
    }
  ],
  "search_strategy_for_stage2": {
    "primary_search_terms": [],
    "network_databases": ["Cisco", "Fortinet", "Juniper", "CDW", "SHI"],
    "recommended_approach": "Focus on enterprise networking distributors and manufacturer documentation"
  }
}

CRITICAL REQUIREMENTS:
1. Validate user's product type classification against technical evidence
2. Apply deep networking expertise to equipment identification
3. Distinguish clearly between complete devices and replacement parts
4. Use Fortinet-specific knowledge for accurate classification
5. Include user type validation in confidence assessment

Return ONLY valid JSON (no markdown, no explanations).
PROMPT;
    }

    /**
     * Get Industrial Automation prompt (detailed)
     */
    protected function getIndustrialAutomationPrompt(): string
    {
        return <<<'PROMPT'
EXPERT INDUSTRIAL AUTOMATION & MOTION CONTROL COMPONENT REVERSE ENGINEERING

SPECIALIST CONTEXT: You are a senior automation engineer with 20+ years experience in industrial control systems, motion control, PLCs, drives, and automation components.

USER CLASSIFICATION CONTEXT:
User Category: {product_category}
User Type: {product_type}
Expected: {user_type_description}

CRITICAL ANALYSIS REQUIREMENTS:
1. User expects this to be a "{product_type}" - validate this classification
2. Focus your identification on {user_type_description}
3. If technical evidence conflicts with user classification, note the discrepancy
4. Consider user type when determining product specifications and use cases

COMPONENT INFORMATION:
- Manufacturer/Brand: {brand}
- Part Number/Model: {manufacturer_part_number}
- Product Number: {product_number}
- Model Number: {model_number}
- Component Context: {user_notes}
- Additional Identifiers: {keywords}
- Condition: {condition}

EXPERT REVERSE ENGINEERING ANALYSIS:

1. **AUTOMATION COMPONENT CLASSIFICATION:**
   - **COMPLETE UNITS**: PLCs (Allen-Bradley ControlLogix), HMI panels, complete servo systems, standalone VFDs
   - **REPLACEMENT PARTS**: Power supplies, I/O cards, memory modules, communication cards, cooling fans
   - **INTERNAL COMPONENTS**: CPU modules, specialty cards, internal bus connectors
   - **ACCESSORIES**: Mounting hardware, cables, programming dongles, expansion racks

2. **MANUFACTURER PART NUMBER DECODING:**
   - Allen-Bradley: 1756-xxxx (ControlLogix), 1769-xxxx (Compact I/O)
   - Siemens: 6ES7-xxx-xxxx (S7 series), 6SL3-xxx-xxxx (SINAMICS drives)
   - Schneider: 140-xxx-xxxx (Modicon Quantum), ATV-xxxx (Altivar drives)
   - Omron: CJ2M-xxx, CP1L-xxx, NJ-xxx series
   - Mitsubishi: FX5U, Q-series, iQ-R series
   - Fanuc: A06B-xxxx for servo components

3. **MOTION CONTROL SPECIFICS:**
   - Servo drives: Power rating, voltage, communication protocol
   - Servo motors: Frame size, torque rating, encoder type
   - Encoders: Incremental vs absolute, resolution, interface

Return expert analysis with user type validation:

{
  "identification_results": {
    "product_identified": true/false,
    "identification_confidence": "high/medium/low/none",
    "user_type_validation": {
      "user_expected_type": "{product_type}",
      "ai_determined_type": "complete_unit/replacement_part/component/accessory",
      "type_classification_matches": true/false,
      "type_confidence": "high/medium/low",
      "type_reasoning": "Explanation based on part numbering and technical analysis"
    },
    "most_likely_product": {
      "complete_part_number": "",
      "product_name": "",
      "manufacturer": "",
      "product_type": "Complete_PLC/Replacement_IO_Card/Component_CPU_Module/Accessory_Cable/Other",
      "specifications": {
        "voltage_rating": "",
        "power_rating": "",
        "communication_protocols": [],
        "io_count": ""
      }
    },
    "reasoning": "",
    "confidence_factors": []
  },
  "technical_specifications": {
    "electrical": {},
    "mechanical": {},
    "communication": {}
  },
  "search_strategy_for_stage2": {
    "primary_search_terms": [],
    "recommended_databases": ["Automation Direct", "Allied Electronics", "RS Components"],
    "recommended_approach": ""
  }
}

Return ONLY valid JSON.
PROMPT;
    }

    /**
     * Get generic expert prompt for other categories
     */
    protected function getGenericExpertPrompt(string $categoryName, string $expertType): string
    {
        return <<<PROMPT
EXPERT {$categoryName} REVERSE ENGINEERING

SPECIALIST CONTEXT: You are a senior {$expertType} with 15+ years experience in this field.

USER CLASSIFICATION CONTEXT:
User Category: {product_category}
User Type: {product_type}
Expected: {user_type_description}

CRITICAL: Validate user's product type classification against technical evidence.

COMPONENT INFORMATION:
- Manufacturer/Brand: {brand}
- Part Number/Model: {manufacturer_part_number}
- Product Number: {product_number}
- Model Number: {model_number}
- Component Context: {user_notes}
- Additional Identifiers: {keywords}
- Condition: {condition}

EXPERT ANALYSIS REQUIREMENTS:
1. Identify the exact product based on part numbers and manufacturer information
2. Validate whether this is a complete unit, replacement part, or accessory
3. Cross-reference with known manufacturer part numbering schemes
4. Consider condition and completeness

Return your expert analysis in this JSON structure:

{
  "identification_results": {
    "product_identified": true/false,
    "identification_confidence": "high/medium/low/none",
    "user_type_validation": {
      "user_expected_type": "{product_type}",
      "ai_determined_type": "complete_unit/replacement_part/component/accessory",
      "type_classification_matches": true/false,
      "type_confidence": "high/medium/low",
      "type_reasoning": "Explanation of classification"
    },
    "most_likely_product": {
      "complete_part_number": "",
      "product_name": "",
      "manufacturer": "",
      "product_type": "",
      "specifications": {}
    },
    "reasoning": "",
    "confidence_factors": []
  },
  "technical_specifications": {},
  "search_strategy_for_stage2": {
    "primary_search_terms": [],
    "recommended_approach": ""
  }
}

Return ONLY valid JSON.
PROMPT;
    }

    /**
     * Get advanced general prompt (fallback)
     */
    protected function getAdvancedGeneralPrompt(): string
    {
        return <<<'PROMPT'
EXPERT PRODUCT IDENTIFICATION & REVERSE ENGINEERING

SPECIALIST CONTEXT: You are a product identification expert with broad knowledge across multiple industries.

USER CLASSIFICATION CONTEXT:
User Category: {product_category}
User Type: {product_type}
Expected: {user_type_description}

COMPONENT INFORMATION:
- Manufacturer/Brand: {brand}
- Part Number/Model: {manufacturer_part_number}
- Product Number: {product_number}
- Model Number: {model_number}
- Component Context: {user_notes}
- Additional Identifiers: {keywords}
- Condition: {condition}

ANALYSIS APPROACH:
1. Analyze manufacturer part number patterns
2. Research brand-specific naming conventions
3. Determine product category and type
4. Validate user's classification against technical evidence

Return your analysis in this JSON structure:

{
  "identification_results": {
    "product_identified": true/false,
    "identification_confidence": "high/medium/low/none",
    "user_type_validation": {
      "user_expected_type": "{product_type}",
      "ai_determined_type": "complete_unit/replacement_part/component/accessory",
      "type_classification_matches": true/false,
      "type_confidence": "high/medium/low",
      "type_reasoning": ""
    },
    "most_likely_product": {
      "complete_part_number": "",
      "product_name": "",
      "manufacturer": "",
      "product_type": "",
      "likely_category": "",
      "specifications": {}
    },
    "reasoning": "",
    "confidence_factors": []
  },
  "technical_specifications": {},
  "search_strategy_for_stage2": {
    "primary_search_terms": [],
    "recommended_approach": ""
  }
}

Return ONLY valid JSON.
PROMPT;
    }

    /**
     * Get Stage 2 Research prompt
     */
    protected function getStage2ResearchPrompt(): string
    {
        return <<<'PROMPT'
STAGE 2: DETAILED PRODUCT RESEARCH

Based on the initial identification, perform detailed research on this product.

PRODUCT INFORMATION:
- Brand: {brand}
- Part Number: {manufacturer_part_number}
- Model: {model_number}
- Category: {product_category}
- Type: {product_type}
- Notes: {user_notes}

RESEARCH TASKS:
1. Find exact product specifications from manufacturer documentation
2. Locate current market pricing (new and used)
3. Identify compatible systems and common applications
4. Find replacement or alternative part numbers
5. Gather technical documentation links

Return detailed research in this JSON structure:

{
  "research_results": {
    "product_confirmed": true/false,
    "confidence": "high/medium/low",
    "official_product_name": "",
    "manufacturer_url": "",
    "detailed_specifications": {},
    "pricing_research": {
      "msrp": null,
      "typical_used_price_range": {"low": null, "high": null},
      "market_availability": "readily_available/limited/rare/discontinued"
    },
    "compatibility": {
      "compatible_systems": [],
      "replacement_parts": [],
      "alternative_part_numbers": []
    },
    "documentation": {
      "datasheet_url": "",
      "manual_url": "",
      "support_url": ""
    }
  },
  "ebay_listing_recommendations": {
    "suggested_category_ids": [],
    "key_item_specifics": {},
    "pricing_recommendation": {
      "suggested_price": null,
      "pricing_strategy": ""
    }
  }
}

Return ONLY valid JSON.
PROMPT;
    }

    /**
     * Get Stage 2 eBay Enrichment prompt
     */
    protected function getStage2EbayPrompt(): string
    {
        return <<<'PROMPT'
STAGE 2: EBAY LISTING ENRICHMENT

Create optimized eBay listing content for this product.

PRODUCT INFORMATION:
- Brand: {brand}
- Part Number: {manufacturer_part_number}
- Model: {model_number}
- Condition: {condition}
- Category: {product_category}
- Type: {product_type}
- Notes: {user_notes}

EBAY OPTIMIZATION REQUIREMENTS:
1. Create compelling title (80 characters max, front-load keywords)
2. Generate comprehensive item specifics
3. Write detailed description sections
4. Suggest optimal categories
5. Recommend search keywords

Return eBay-optimized content in this JSON structure:

{
  "listing_optimization": {
    "title": "",
    "subtitle": "",
    "item_specifics": {
      "Brand": "",
      "MPN": "",
      "Model": "",
      "Type": "",
      "Condition": "",
      "Country/Region of Manufacture": ""
    },
    "description": {
      "overview": "",
      "features": [],
      "specifications": "",
      "condition_details": "",
      "whats_included": "",
      "shipping_info": ""
    },
    "category_suggestions": [
      {
        "category_id": "",
        "category_name": "",
        "confidence": "high/medium/low"
      }
    ],
    "search_optimization": {
      "primary_keywords": [],
      "secondary_keywords": [],
      "negative_keywords": []
    },
    "pricing_guidance": {
      "market_price_range": {"low": null, "high": null},
      "recommended_starting_price": null,
      "buy_it_now_price": null,
      "pricing_notes": ""
    }
  }
}

Return ONLY valid JSON.
PROMPT;
    }

    /**
     * Get display name for category slug
     */
    protected function getCategoryDisplayName(string $slug): string
    {
        $names = [
            'industrial_automation_motion_controls' => 'Industrial Automation & Motion Controls',
            'hydraulics_pneumatics' => 'Hydraulics & Pneumatics',
            'electrical_equipment_supplies' => 'Electrical Equipment & Supplies',
            'test_measurement_equipment' => 'Test & Measurement Equipment',
            'cnc_metalworking' => 'CNC & Metalworking',
            'healthcare_medical_equipment' => 'Healthcare & Medical Equipment',
            'computer_enterprise_it' => 'Computer & Enterprise IT',
            'vintage_computing' => 'Vintage Computing',
            'consumer_electronics' => 'Consumer Electronics',
            'automotive_parts_motors' => 'Automotive Parts & Motors',
            'musical_instruments_audio' => 'Musical Instruments & Audio',
            'cameras_photography' => 'Cameras & Photography',
            'hvac_refrigeration' => 'HVAC & Refrigeration',
            'restaurant_food_service' => 'Restaurant & Food Service',
            'material_handling' => 'Material Handling',
            'heavy_equipment' => 'Heavy Equipment',
            'printing_graphic_arts' => 'Printing & Graphic Arts',
            'pos_retail_systems' => 'POS & Retail Systems',
            'agriculture_forestry' => 'Agriculture & Forestry',
            'video_games_consoles' => 'Video Games & Consoles',
            'sporting_goods_fitness' => 'Sporting Goods & Fitness',
            'toys_collectibles' => 'Toys & Collectibles',
            'crafts_hobby' => 'Crafts & Hobby',
            'books_magazines' => 'Books & Magazines',
            'art_antiques' => 'Art & Antiques',
            'cell_phones_accessories' => 'Cell Phones & Accessories',
            'home_garden' => 'Home & Garden',
            'health_beauty' => 'Health & Beauty',
            'pet_supplies' => 'Pet Supplies',
            'advanced_general_product' => 'General Product',
        ];

        return $names[$slug] ?? Str::title(str_replace('_', ' ', $slug));
    }
}
