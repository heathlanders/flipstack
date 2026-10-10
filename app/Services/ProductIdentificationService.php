<?php

namespace App\Services;

use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Integration\IntegrationCredential;
use App\Services\AI\PromptService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * COMPREHENSIVE Product Identification Service - Expert Reverse Engineering
 *
 * Now uses user-provided classification when available for better accuracy.
 * Covers ALL product categories with expert-level analysis for each field.
 * Supports database-stored prompts with fallback to hardcoded prompts.
 */
class ProductIdentificationService
{
    protected ?string $apiKey = null;

    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    protected ?PromptService $promptService = null;

    public function __construct(?PromptService $promptService = null)
    {
        $this->promptService = $promptService;
        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        try {
            $credential = IntegrationCredential::where('platform', 'gemini')
                ->where('environment', 'production')
                ->where('is_active', true)
                ->first();

            if ($credential) {
                $this->apiKey = $credential->decrypted_client_secret;
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to load Gemini credentials', ['error' => $e->getMessage()]);
        }
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Main identification method - uses user classification when available.
     *
     * @param  array  $marketEvidence  Real marketplace listing titles for this product's
     *                                 identifier, used to ground identification against
     *                                 reality instead of the model's parametric prior.
     */
    public function identify(ProductIntakeQueue $item, array $marketEvidence = []): ?array
    {
        if (! $this->isAvailable()) {
            Log::warning('Comprehensive product identification unavailable - no Gemini API key');

            return null;
        }

        // CHECK FOR USER-PROVIDED CATEGORY FIRST
        if (! empty($item->product_category)) {
            // Map user category directly to expert category
            $expertCategory = $this->mapUserCategoryToExpertCategory($item->product_category);

            Log::info('Using User-Selected Category for Expert Prompt', [
                'queue_item_id' => $item->id,
                'user_selected_category' => $item->product_category,
                'user_selected_type' => $item->product_type,
                'mapped_expert_category' => $expertCategory,
                'brand' => $item->brand,
                'manufacturer_part_number' => $item->manufacturer_part_number,
            ]);

            $category = $expertCategory;
        } else {
            // FALLBACK: Auto-detect only if user didn't provide category
            $category = $this->detectComprehensiveProductCategory($item);

            Log::info('Auto-detecting category (no user selection)', [
                'queue_item_id' => $item->id,
                'detected_category' => $category,
                'brand' => $item->brand,
                'manufacturer_part_number' => $item->manufacturer_part_number,
            ]);
        }

        // Normalize evidence to a clean list of title strings.
        $evidenceTitles = $this->normalizeMarketEvidence($marketEvidence);

        // Cache for 24 hours. Evidence is part of the key so a different marketplace
        // snapshot produces a fresh identification rather than a stale parametric one.
        $cacheKey = 'expert_identification_'.md5(
            $item->id.$item->updated_at.$category.($item->product_category ?? '').implode('|', $evidenceTitles)
        );

        return Cache::remember($cacheKey, 60 * 24, function () use ($item, $category, $evidenceTitles) {
            return $this->performExpertIdentification($item, $category, $evidenceTitles);
        });
    }

    /**
     * Normalize raw market evidence (listing arrays or strings) to a deduped list
     * of non-empty title strings, capped to keep the prompt tight.
     */
    protected function normalizeMarketEvidence(array $marketEvidence): array
    {
        $titles = [];

        foreach ($marketEvidence as $entry) {
            $title = is_array($entry) ? ($entry['title'] ?? '') : (string) $entry;
            $title = trim((string) $title);

            if ($title !== '') {
                $titles[] = $title;
            }
        }

        return array_slice(array_values(array_unique($titles)), 0, 15);
    }

    /**
     * Build an authoritative evidence block from real marketplace listing titles.
     * The model is told to identify FROM this evidence, not from memory.
     */
    protected function buildMarketEvidenceBlock(array $evidenceTitles): string
    {
        if (empty($evidenceTitles)) {
            return '';
        }

        $lines = implode("\n", array_map(fn ($t) => '- '.$t, $evidenceTitles));

        return <<<EVIDENCE
REAL MARKETPLACE LISTINGS FOR THIS IDENTIFIER (AUTHORITATIVE GROUND TRUTH):
The following are actual eBay listing titles found by searching this product's exact
identifier. They reflect what this product really is. Identify the product FROM this
evidence and the user-provided description — do NOT override it with general knowledge
of a similarly-named brand. If these listings clearly contradict a famous brand
association, trust the listings.

{$lines}

END OF MARKETPLACE EVIDENCE.

EVIDENCE;
    }

    /**
     * Map user form categories to existing expert prompt categories
     */
    protected function mapUserCategoryToExpertCategory(string $userCategory): string
    {
        // Map user selections to your existing expert prompt categories
        $categoryMapping = [
            // Network & IT Equipment
            'network_equipment' => 'computer_enterprise_it',
            'computer_hardware' => 'computer_enterprise_it',
            'server_equipment' => 'computer_enterprise_it',
            'security_equipment' => 'computer_enterprise_it',
            'peripherals' => 'computer_enterprise_it',
            'telecom_equipment' => 'computer_enterprise_it',

            // POS & Retail
            'pos_equipment' => 'pos_retail_systems',

            // Industrial & Automation
            'industrial_equipment' => 'industrial_automation_motion_controls',
            'test_measurement' => 'test_measurement_equipment',
            'electrical_supplies' => 'electrical_equipment_supplies',
            'hydraulics_pneumatics' => 'hydraulics_pneumatics',
            'cnc_metalworking' => 'cnc_metalworking',
            'material_handling' => 'material_handling',
            'heavy_equipment' => 'heavy_equipment',

            // Healthcare & Medical
            'medical_equipment' => 'healthcare_medical_equipment',

            // Automotive
            'automotive_parts' => 'automotive_parts_motors',

            // HVAC & Food Service
            'hvac_refrigeration' => 'hvac_refrigeration',
            'restaurant_food_service' => 'restaurant_food_service',

            // Printing & Graphics
            'printing_graphic_arts' => 'printing_graphic_arts',

            // Agriculture
            'agriculture_forestry' => 'agriculture_forestry',

            // Consumer & Entertainment
            'video_games_consoles' => 'video_games_consoles',
            'sporting_goods_fitness' => 'sporting_goods_fitness',
            'toys_collectibles' => 'toys_collectibles',
            'consumer_electronics' => 'consumer_electronics',
            'musical_instruments_audio' => 'musical_instruments_audio',
            'cameras_photography' => 'cameras_photography',

            // Home & Personal
            'crafts_hobby' => 'crafts_hobby',
            'books_magazines' => 'books_magazines',
            'art_antiques' => 'art_antiques',
            'cell_phones_accessories' => 'cell_phones_accessories',
            'home_garden' => 'home_garden',
            'health_beauty' => 'health_beauty',
            'pet_supplies' => 'pet_supplies',

            // Fallback
            'other' => 'advanced_general_product',
        ];

        $mappedCategory = $categoryMapping[$userCategory] ?? 'advanced_general_product';

        Log::info('Category Mapping Applied', [
            'user_category' => $userCategory,
            'expert_category' => $mappedCategory,
        ]);

        return $mappedCategory;
    }

    /**
     * Get human-readable description of user product type
     */
    protected function getUserTypeDescription(?string $userType): string
    {
        if (empty($userType)) {
            return 'Type not specified';
        }

        $descriptions = [
            'complete_unit' => 'Complete, standalone system or device that operates independently',
            'replacement_part' => 'Replacement part or spare component for existing systems',
            'component' => 'Internal component or module that goes inside other equipment',
            'accessory' => 'Accessory or add-on item that enhances main equipment',
            'consumable' => 'Consumable supply item that gets used up during operation',
            'upgrade_module' => 'Upgrade or expansion module that adds functionality',
            'cable_connector' => 'Cable, connector, or wiring component',
            'software_media' => 'Software, firmware, or media item',
            'documentation' => 'Documentation, manual, or instructional material',
            'packaging_only' => 'Original packaging only (no actual product)',
        ];

        return $descriptions[$userType] ?? 'Unspecified product type';
    }

    /**
     * Comprehensive category detection covering all seller's eBay categories
     */
    protected function detectComprehensiveProductCategory(ProductIntakeQueue $item): string
    {
        $allText = strtolower(implode(' ', [
            $item->brand ?? '',
            $item->manufacturer_part_number ?? '',
            $item->product_number ?? '',
            $item->model_number ?? '',
            $item->user_notes ?? '',
            implode(' ', $item->keywords ?? []),
        ]));

        // INDUSTRIAL AUTOMATION & MOTION CONTROLS
        if (preg_match('/\b(servo|stepper|encoder|resolver|linear.*actuator|rotary.*actuator|motion.*control|plc|hmi|drive|inverter|vfd|variable.*frequency)\b/', $allText) ||
            preg_match('/\b(automation|siemens|allen.*bradley|omron|schneider|mitsubishi|fanuc|abb|parker|festo)\b/', $allText)) {
            return 'industrial_automation_motion_controls';
        }

        // HYDRAULICS & PNEUMATICS
        if (preg_match('/\b(hydraulic|pneumatic|valve|cylinder|pump|compressor|manifold|fitting|hose|actuator|pressure|psi|bar|cfm)\b/', $allText) ||
            preg_match('/\b(parker|festo|smc|norgren|bosch.*rexroth|eaton|hydac|sun.*hydraulics|ph|ph.*parker)\b/', $allText)) {
            return 'hydraulics_pneumatics';
        }

        // ELECTRICAL EQUIPMENT & SUPPLIES
        if (preg_match('/\b(contactor|relay|breaker|fuse|switch|panel|transformer|meter|multimeter|oscilloscope|power.*supply)\b/', $allText) ||
            preg_match('/\b(schneider|abb|eaton|cutler.*hammer|square.*d|ge|westinghouse|siemens|honeywell|fluke)\b/', $allText)) {
            return 'electrical_equipment_supplies';
        }

        // TEST & MEASUREMENT EQUIPMENT
        if (preg_match('/\b(oscilloscope|multimeter|spectrum.*analyzer|signal.*generator|logic.*analyzer|probe|calibrator|tester|meter)\b/', $allText) ||
            preg_match('/\b(fluke|tektronix|keysight|agilent|rohde.*schwarz|yokogawa|hioki|rigol|lecroy)\b/', $allText)) {
            return 'test_measurement_equipment';
        }

        // CNC & METALWORKING
        if (preg_match('/\b(cnc|lathe|mill|machining|spindle|tool.*holder|collet|chuck|vise|indicator|micrometer|caliper)\b/', $allText) ||
            preg_match('/\b(haas|mazak|okuma|dmg.*mori|fadal|bridgeport|hardinge|doosan|starrett|mitutoyo)\b/', $allText)) {
            return 'cnc_metalworking';
        }

        // HEALTHCARE & MEDICAL EQUIPMENT
        if (preg_match('/\b(patient.*monitor|defibrillator|ultrasound|x.*ray|mri|ct|surgical|anesthesia|ventilator|infusion.*pump)\b/', $allText) ||
            preg_match('/\b(philips|ge.*healthcare|siemens.*healthineers|medtronic|abbott|boston.*scientific|stryker|zimmer)\b/', $allText)) {
            return 'healthcare_medical_equipment';
        }

        // COMPUTER COMPONENTS & ENTERPRISE IT
        if (preg_match('/\b(server|switch|router|firewall|storage|raid|san|nas|blade|rack|psu|power.*supply|memory|dimm|cpu|processor)\b/', $allText) ||
            preg_match('/\b(cisco|juniper|dell|hp|hpe|lenovo|ibm|netapp|emc|supermicro|intel|amd|nvidia|fortinet)\b/', $allText)) {
            return 'computer_enterprise_it';
        }

        // VINTAGE COMPUTING
        if (preg_match('/\b(vintage|retro|classic|old|antique|8.*bit|16.*bit|commodore|atari|apple.*ii|tandy|ibm.*pc|dos)\b/', $allText) ||
            preg_match('/\b(commodore|atari|apple.*ii|tandy|kaypro|osborne|compaq|dec|sun.*microsystems)\b/', $allText)) {
            return 'vintage_computing';
        }

        // CONSUMER ELECTRONICS
        if (preg_match('/\b(tv|television|monitor|receiver|amplifier|speaker|headphone|bluetooth|wifi|usb|hdmi|remote)\b/', $allText) ||
            preg_match('/\b(sony|samsung|lg|panasonic|pioneer|yamaha|denon|marantz|onkyo|bose|jbl|klipsch)\b/', $allText)) {
            return 'consumer_electronics';
        }

        // AUTOMOTIVE PARTS & MOTORS
        if (preg_match('/\b(engine|motor|transmission|brake|suspension|exhaust|alternator|starter|fuel.*pump|radiator|ecu|pcm)\b/', $allText) ||
            preg_match('/\b(gm|ford|chrysler|toyota|honda|nissan|bmw|mercedes|audi|volkswagen|bosch|delphi|denso)\b/', $allText)) {
            return 'automotive_parts_motors';
        }

        // MUSICAL INSTRUMENTS & AUDIO
        if (preg_match('/\b(guitar|bass|drum|keyboard|piano|synthesizer|microphone|mixer|amplifier|speaker|studio|recording)\b/', $allText) ||
            preg_match('/\b(fender|gibson|yamaha|roland|korg|marshall|ampeg|shure|audio.*technica|akg|neumann)\b/', $allText)) {
            return 'musical_instruments_audio';
        }

        // CAMERAS & PHOTOGRAPHY
        if (preg_match('/\b(camera|lens|flash|tripod|filter|viewfinder|shutter|aperture|exposure|iso|macro|telephoto)\b/', $allText) ||
            preg_match('/\b(canon|nikon|sony|fuji|leica|pentax|olympus|panasonic|sigma|tamron|zeiss)\b/', $allText)) {
            return 'cameras_photography';
        }

        // HVAC & REFRIGERATION
        if (preg_match('/\b(hvac|air.*conditioning|heating|cooling|refrigeration|compressor|evaporator|condenser|thermostat|duct)\b/', $allText) ||
            preg_match('/\b(carrier|trane|york|lennox|rheem|goodman|copeland|danfoss|emerson|honeywell)\b/', $allText)) {
            return 'hvac_refrigeration';
        }

        // RESTAURANT & FOOD SERVICE
        if (preg_match('/\b(commercial.*kitchen|restaurant|food.*service|oven|grill|fryer|freezer|refrigerator|dishwasher|mixer)\b/', $allText) ||
            preg_match('/\b(hobart|vulcan|garland|true|beverage.*air|imperial|blodgett|rational|middleby)\b/', $allText)) {
            return 'restaurant_food_service';
        }

        // MATERIAL HANDLING
        if (preg_match('/\b(forklift|conveyor|hoist|crane|lift.*truck|pallet.*jack|warehouse|sorting|packaging)\b/', $allText) ||
            preg_match('/\b(toyota|crown|yale|hyster|clark|raymond|nissan|caterpillar|komatsu|terex)\b/', $allText)) {
            return 'material_handling';
        }

        // HEAVY EQUIPMENT
        if (preg_match('/\b(excavator|bulldozer|loader|backhoe|crane|grader|compactor|roller|scraper|dump.*truck)\b/', $allText) ||
            preg_match('/\b(caterpillar|komatsu|john.*deere|case|new.*holland|volvo|liebherr|hitachi|kobelco)\b/', $allText)) {
            return 'heavy_equipment';
        }

        // PRINTING & GRAPHIC ARTS
        if (preg_match('/\b(printer|printing|press|offset|digital|wide.*format|plotter|rip|color.*management|bindery)\b/', $allText) ||
            preg_match('/\b(heidelberg|komori|xerox|hp.*indigo|canon.*oce|epson|roland|mutoh|mimaki)\b/', $allText)) {
            return 'printing_graphic_arts';
        }

        // POS & RETAIL SYSTEMS
        if (preg_match('/\b(pos|point.*of.*sale|cash.*register|terminal|receipt.*printer|barcode.*scanner|credit.*card|payment)\b/', $allText) ||
            preg_match('/\b(ncr|ibm|micros|aloha|square|verifone|ingenico|symbol|honeywell|zebra)\b/', $allText)) {
            return 'pos_retail_systems';
        }

        // AGRICULTURE & FORESTRY
        if (preg_match('/\b(tractor|combine|harvester|planter|cultivator|mower|baler|sprayer|irrigation|forestry)\b/', $allText) ||
            preg_match('/\b(john.*deere|case.*ih|new.*holland|massey.*ferguson|kubota|fendt|claas|agco)\b/', $allText)) {
            return 'agriculture_forestry';
        }

        // VIDEO GAMES & CONSOLES
        if (preg_match('/\b(console|gaming|playstation|xbox|nintendo|arcade|controller|joystick|cartridge|game)\b/', $allText) ||
            preg_match('/\b(sony.*playstation|microsoft.*xbox|nintendo|sega|atari|neo.*geo|capcom|konami)\b/', $allText)) {
            return 'video_games_consoles';
        }

        // SPORTING GOODS & FITNESS
        if (preg_match('/\b(fitness|exercise|treadmill|bicycle|golf|tennis|basketball|football|baseball|hockey|skiing)\b/', $allText) ||
            preg_match('/\b(nike|adidas|wilson|spalding|titleist|callaway|trek|specialized|life.*fitness|nautilus)\b/', $allText)) {
            return 'sporting_goods_fitness';
        }

        // TOYS & COLLECTIBLES
        if (preg_match('/\b(toy|action.*figure|doll|model|collectible|vintage|antique|trading.*card|comic|memorabilia)\b/', $allText) ||
            preg_match('/\b(mattel|hasbro|lego|disney|star.*wars|marvel|pokemon|magic.*gathering|topps)\b/', $allText)) {
            return 'toys_collectibles';
        }

        // CRAFTS & HOBBY
        if (preg_match('/\b(craft|hobby|sewing|knitting|woodworking|jewelry.*making|scrapbook|model.*making|rc|remote.*control)\b/', $allText) ||
            preg_match('/\b(brother|singer|janome|dremel|proxxon|tamiya|traxxas|horizon.*hobby)\b/', $allText)) {
            return 'crafts_hobby';
        }

        // BOOKS & MAGAZINES
        if (preg_match('/\b(book|magazine|manual|guide|textbook|novel|reference|encyclopedia|journal|publication)\b/', $allText) ||
            preg_match('/\b(isbn|publisher|edition|volume|issue|subscription|library|academic)\b/', $allText)) {
            return 'books_magazines';
        }

        // ART & ANTIQUES
        if (preg_match('/\b(art|painting|sculpture|antique|vintage|pottery|glass|ceramic|crystal|silver|gold|jewelry)\b/', $allText) ||
            preg_match('/\b(original|signed|limited.*edition|authentication|appraisal|estate|auction)\b/', $allText)) {
            return 'art_antiques';
        }

        // CELL PHONES & ACCESSORIES
        if (preg_match('/\b(phone|smartphone|cell.*phone|mobile|iphone|android|case|charger|screen.*protector|sim)\b/', $allText) ||
            preg_match('/\b(apple|samsung|google|huawei|lg|motorola|oneplus|xiaomi|nokia)\b/', $allText)) {
            return 'cell_phones_accessories';
        }

        // HOME & GARDEN
        if (preg_match('/\b(lawn.*mower|garden|outdoor|patio|furniture|tool|shed|greenhouse|irrigation|landscaping)\b/', $allText) ||
            preg_match('/\b(craftsman|black.*decker|ryobi|milwaukee|dewalt|makita|husqvarna|toro|ariens)\b/', $allText)) {
            return 'home_garden';
        }

        // HEALTH & BEAUTY
        if (preg_match('/\b(cosmetic|beauty|skincare|hair.*care|fragrance|perfume|makeup|nail|spa|salon)\b/', $allText) ||
            preg_match('/\b(loreal|revlon|maybelline|estee.*lauder|clinique|mac|olay|neutrogena)\b/', $allText)) {
            return 'health_beauty';
        }

        // PET SUPPLIES
        if (preg_match('/\b(pet|dog|cat|bird|fish|aquarium|collar|leash|cage|food|treat|toy|grooming)\b/', $allText) ||
            preg_match('/\b(purina|pedigree|whiskas|iams|science.*diet|royal.*canin|kong|petmate)\b/', $allText)) {
            return 'pet_supplies';
        }

        // Default to advanced general analysis if no specific category detected
        return 'advanced_general_product';
    }

    /**
     * Perform expert identification based on detected category
     */
    protected function performExpertIdentification(ProductIntakeQueue $item, string $category, array $evidenceTitles = []): ?array
    {
        $prompt = $this->buildExpertCategoryPrompt($item, $category);

        // Prepend real marketplace evidence so identification is grounded in reality
        // (applies to every prompt variant: expert, generic, and DB-stored).
        $evidenceBlock = $this->buildMarketEvidenceBlock($evidenceTitles);
        if ($evidenceBlock !== '') {
            $prompt = $evidenceBlock."\n".$prompt;
        }

        try {
            Log::info('Expert category analysis', [
                'category' => $category,
                'expert_level' => 'professional_reverse_engineering',
            ]);

            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->retry(3, fn (int $attempt) => $attempt * 2000, function ($exception) {
                // Gemini 503 (overloaded) / 429 (rate limit) are transient; retry with backoff
                // instead of dropping the item straight to the manual-research template.
                return $exception instanceof ConnectionException
                    || in_array($exception->response?->status(), [429, 503], true);
            }, throw: false)->timeout(120)->post("{$this->baseUrl}/gemini-2.5-flash:generateContent", [
                'contents' => [[
                    'parts' => [['text' => $prompt]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'topP' => 0.7,
                    'topK' => 20,
                ],
            ]);

            if (! $response->successful()) {
                Log::error('Expert identification failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $content = $response->json('candidates.0.content.parts.0.text');

            if (! $content) {
                Log::warning('Expert identification returned empty content');

                return null;
            }

            $jsonContent = $this->extractJson($content);
            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse expert identification JSON', [
                    'error' => json_last_error_msg(),
                    'content_preview' => substr($content, 0, 500),
                ]);

                return null;
            }

            // Add expert metadata
            $data['expert_analysis'] = true;
            $data['category_expertise'] = $category;
            $data['identification_stage'] = 'expert_stage_1_complete';
            $data['analysis_timestamp'] = now()->toISOString();
            $data['queue_item_id'] = $item->id;
            $data['user_provided_classification'] = ! empty($item->product_category);

            Log::info('Expert Category Identification Complete', [
                'category' => $category,
                'confidence' => $data['identification_results']['identification_confidence'] ?? 'unknown',
                'product_found' => $data['identification_results']['product_identified'] ?? false,
                'user_type_matches' => $data['identification_results']['user_type_validation']['type_classification_matches'] ?? 'unknown',
            ]);

            return $data;

        } catch (\Exception $e) {
            Log::error('Expert identification exception', [
                'category' => $category,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build expert category-specific identification prompts
     *
     * First attempts to load from database via PromptService, falls back to hardcoded prompts.
     */
    protected function buildExpertCategoryPrompt(ProductIntakeQueue $item, string $category): string
    {
        $baseInfo = $this->buildBaseProductInfo($item);

        // Try database prompt first if PromptService is available
        if ($this->promptService) {
            $orgId = $item->team?->organization_id;
            $dbPrompt = $this->promptService->getPromptForCategory('stage1_identification', $category, $orgId);

            if ($dbPrompt) {
                Log::debug('Using database prompt for identification', [
                    'prompt_id' => $dbPrompt->id,
                    'category' => $category,
                    'is_system' => $dbPrompt->is_system,
                ]);

                return $this->promptService->renderPrompt($dbPrompt, $baseInfo);
            }
        }

        // Fallback to hardcoded prompts
        Log::debug('Using hardcoded prompt for identification', ['category' => $category]);

        return match ($category) {
            'industrial_automation_motion_controls' => $this->buildIndustrialAutomationPrompt($baseInfo, $item),
            'hydraulics_pneumatics' => $this->buildHydraulicsPneumaticsPrompt($baseInfo, $item),
            'electrical_equipment_supplies' => $this->buildElectricalEquipmentPrompt($baseInfo, $item),
            'test_measurement_equipment' => $this->buildTestMeasurementPrompt($baseInfo, $item),
            'cnc_metalworking' => $this->buildCNCMetalworkingPrompt($baseInfo, $item),
            'healthcare_medical_equipment' => $this->buildMedicalEquipmentPrompt($baseInfo, $item),
            'computer_enterprise_it' => $this->buildEnterpriseITPrompt($baseInfo, $item),
            'vintage_computing' => $this->buildVintageComputingPrompt($baseInfo, $item),
            'consumer_electronics' => $this->buildConsumerElectronicsPrompt($baseInfo, $item),
            'automotive_parts_motors' => $this->buildAutomotivePrompt($baseInfo, $item),
            'musical_instruments_audio' => $this->buildMusicalInstrumentsPrompt($baseInfo, $item),
            'cameras_photography' => $this->buildCamerasPhotographyPrompt($baseInfo, $item),
            'hvac_refrigeration' => $this->buildHVACPrompt($baseInfo, $item),
            'restaurant_food_service' => $this->buildRestaurantFoodServicePrompt($baseInfo, $item),
            'material_handling' => $this->buildMaterialHandlingPrompt($baseInfo, $item),
            'heavy_equipment' => $this->buildHeavyEquipmentPrompt($baseInfo, $item),
            'printing_graphic_arts' => $this->buildPrintingGraphicArtsPrompt($baseInfo, $item),
            'pos_retail_systems' => $this->buildPOSRetailSystemsPrompt($baseInfo, $item),
            'agriculture_forestry' => $this->buildAgricultureForestryPrompt($baseInfo, $item),
            'video_games_consoles' => $this->buildVideoGamesConsolesPrompt($baseInfo, $item),
            'sporting_goods_fitness' => $this->buildSportingGoodsFitnessPrompt($baseInfo, $item),
            'toys_collectibles' => $this->buildToysCollectiblesPrompt($baseInfo, $item),
            'crafts_hobby' => $this->buildCraftsHobbyPrompt($baseInfo, $item),
            'books_magazines' => $this->buildBooksMagazinesPrompt($baseInfo, $item),
            'art_antiques' => $this->buildArtAntiquesPrompt($baseInfo, $item),
            'cell_phones_accessories' => $this->buildCellPhonesAccessoriesPrompt($baseInfo, $item),
            'home_garden' => $this->buildHomeGardenPrompt($baseInfo, $item),
            'health_beauty' => $this->buildHealthBeautyPrompt($baseInfo, $item),
            'pet_supplies' => $this->buildPetSuppliesPrompt($baseInfo, $item),
            default => $this->buildAdvancedGeneralPrompt($baseInfo, $item),
        };
    }

    /**
     * Build base product information with user type context
     */
    protected function buildBaseProductInfo(ProductIntakeQueue $item): array
    {
        return [
            'brand' => $item->brand ?? 'Unknown',
            'manufacturer_part_number' => $item->manufacturer_part_number ?? 'N/A',
            'product_number' => $item->product_number ?? 'N/A',
            'model_number' => $item->model_number ?? 'N/A',
            'barcode' => $item->barcode ?? 'N/A',
            'user_notes' => $item->user_notes ?? 'No additional information',
            'keywords' => implode(', ', $item->keywords ?? []),
            'condition' => $item->condition ?? 'Unknown',
            'product_category' => $item->product_category ?? 'Unknown',
            'product_type' => $item->product_type ?? 'Unknown',
            'user_type_description' => $this->getUserTypeDescription($item->product_type ?? ''),
        ];
    }

    /**
     * NETWORK EQUIPMENT & ENTERPRISE IT - Expert Analysis with Type Context
     */
    protected function buildEnterpriseITPrompt(array $baseInfo, ProductIntakeQueue $item): string
    {
        $searchTerms = $this->buildSearchTerms($item);

        // BUILD USER TYPE CONTEXT
        $userTypeContext = '';
        if (! empty($baseInfo['product_type']) && $baseInfo['product_type'] !== 'Unknown') {
            $userTypeContext = <<<USERTYPE

USER CLASSIFICATION CONTEXT:
User Category: {$baseInfo['product_category']} 
User Type: {$baseInfo['product_type']}
Expected: {$baseInfo['user_type_description']}

CRITICAL FORTINET ANALYSIS:
- If user expects "complete_unit": Look for main device models (FS-148F, FG-60F, etc.)
- If user expects "replacement_part": Look for component part numbers (P25494-03-01, PWR-xxx, etc.)
- Validate part number pattern against user expectation

USERTYPE;
        }

        return <<<PROMPT
EXPERT NETWORK EQUIPMENT & ENTERPRISE IT REVERSE ENGINEERING

SPECIALIST CONTEXT: You are a senior network engineer with 20+ years experience in enterprise networking, routing/switching, security appliances, and datacenter infrastructure.
{$userTypeContext}

COMPONENT INFORMATION:
- Manufacturer/Brand: {$baseInfo['brand']}
- Part Number/Model: {$baseInfo['manufacturer_part_number']}
- Product Number: {$baseInfo['product_number']}
- Model Number: {$baseInfo['model_number']}
- Component Context: {$baseInfo['user_notes']}
- Additional Identifiers: {$baseInfo['keywords']}
- Condition: {$baseInfo['condition']}
- Search Terms: {$searchTerms}

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
      "user_expected_type": "{$baseInfo['product_type']}",
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
     * INDUSTRIAL AUTOMATION & MOTION CONTROLS - Expert Analysis with Type Context
     */
    protected function buildIndustrialAutomationPrompt(array $baseInfo, ProductIntakeQueue $item): string
    {
        $searchTerms = $this->buildSearchTerms($item);

        // BUILD USER TYPE CONTEXT
        $userTypeContext = '';
        if (! empty($baseInfo['product_type']) && $baseInfo['product_type'] !== 'Unknown') {
            $userTypeContext = <<<USERTYPE

USER CLASSIFICATION CONTEXT:
User Category: {$baseInfo['product_category']}
User Type: {$baseInfo['product_type']} 
Expected: {$baseInfo['user_type_description']}

CRITICAL ANALYSIS REQUIREMENTS:
1. User expects this to be a "{$baseInfo['product_type']}" - validate this classification
2. Focus your identification on {$baseInfo['user_type_description']}
3. If technical evidence conflicts with user classification, note the discrepancy
4. Consider user type when determining product specifications and use cases

USERTYPE;
        }

        return <<<PROMPT
EXPERT INDUSTRIAL AUTOMATION & MOTION CONTROL COMPONENT REVERSE ENGINEERING

SPECIALIST CONTEXT: You are a senior automation engineer with 20+ years experience in industrial control systems, motion control, PLCs, drives, and automation components.
{$userTypeContext}

COMPONENT INFORMATION:
- Manufacturer/Brand: {$baseInfo['brand']}
- Part Number/Model: {$baseInfo['manufacturer_part_number']}
- Product Number: {$baseInfo['product_number']}
- Model Number: {$baseInfo['model_number']}
- Component Context: {$baseInfo['user_notes']}
- Search Terms: {$searchTerms}

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

Return expert analysis with user type validation:

{
  "identification_results": {
    "product_identified": true/false,
    "identification_confidence": "high/medium/low/none",
    "user_type_validation": {
      "user_expected_type": "{$baseInfo['product_type']}",
      "ai_determined_type": "complete_unit/replacement_part/component/accessory",
      "type_classification_matches": true/false,
      "type_confidence": "high/medium/low",
      "type_reasoning": "Explanation based on part numbering and technical analysis"
    },
    "most_likely_product": {
      "complete_part_number": "",
      "product_name": "",
      "manufacturer": "",
      "product_type": "Complete_PLC/Replacement_IO_Card/Component_CPU_Module/Accessory_Cable/Other"
    }
  }
}

Return ONLY valid JSON.
PROMPT;
    }

    /**
     * Build search terms from all available data
     */
    protected function buildSearchTerms(ProductIntakeQueue $item): string
    {
        $terms = array_filter([
            $item->brand,
            $item->manufacturer_part_number,
            $item->product_number,
            $item->model_number,
            $item->barcode,
        ]);

        if (! empty($item->user_notes)) {
            $notesWords = preg_split('/\s+/', trim($item->user_notes));
            foreach ($notesWords as $word) {
                $cleanWord = trim($word);
                if (strlen($cleanWord) >= 3) {
                    $terms[] = $cleanWord;
                }
            }
        }

        if (! empty($item->keywords) && is_array($item->keywords)) {
            $terms = array_merge($terms, $item->keywords);
        }

        return implode(' ', array_unique(array_filter($terms)));
    }

    /**
     * Generic prompt builder with user type context for remaining categories
     */
    protected function buildRemainingExpertPrompts(string $category, array $baseInfo, ProductIntakeQueue $item): string
    {
        $searchTerms = $this->buildSearchTerms($item);
        $categoryInfo = $this->getCategoryExpertiseInfo($category);

        // BUILD USER TYPE CONTEXT
        $userTypeContext = '';
        if (! empty($baseInfo['product_type']) && $baseInfo['product_type'] !== 'Unknown') {
            $userTypeContext = <<<USERTYPE

USER CLASSIFICATION CONTEXT:
User Category: {$baseInfo['product_category']}
User Type: {$baseInfo['product_type']}
Expected: {$baseInfo['user_type_description']}

CRITICAL: Validate user's product type classification against technical evidence.

USERTYPE;
        }

        return <<<PROMPT
EXPERT {$categoryInfo['title']} REVERSE ENGINEERING

SPECIALIST CONTEXT: You are a senior {$categoryInfo['expert_type']} with {$categoryInfo['experience']}.
{$userTypeContext}

COMPONENT INFORMATION:
- Manufacturer/Brand: {$baseInfo['brand']}
- Part Number: {$baseInfo['manufacturer_part_number']}
- User Category: {$baseInfo['product_category']}
- Keywords: {$baseInfo['keywords']}
- Search Terms: {$searchTerms}

USER-PROVIDED DESCRIPTION (AUTHORITATIVE — do NOT contradict this):
{$baseInfo['user_notes']}

BRAND DISAMBIGUATION (CRITICAL):
A brand name can belong to multiple unrelated companies across different industries.
Use the user description, category, and any marketplace evidence above to decide WHICH
company/industry this part belongs to. Interpret the brand in THAT industry. Do NOT
default to the most globally famous company that shares the name when the context points
elsewhere (e.g. an HVAC furnace part is not a computer component just because the brand
name is also used by a PC maker).

ANTI-HALLUCINATION (CRITICAL):
Identify ONLY from the evidence and context provided. If you cannot confidently resolve
this exact part number to a real product, set "product_identified" to false and
"identification_confidence" to "low" or "none". DO NOT invent a plausible-sounding product,
spec, or model. An honest "unknown" is correct; a confident wrong answer is a failure.

{
  "identification_results": {
    "product_identified": true/false,
    "identification_confidence": "high/medium/low/none",
    "user_type_validation": {
      "user_expected_type": "{$baseInfo['product_type']}",
      "ai_determined_type": "",
      "type_classification_matches": true/false,
      "type_confidence": "high/medium/low",
      "type_reasoning": ""
    },
    "most_likely_product": {
      "complete_part_number": "",
      "product_name": "",
      "manufacturer": "",
      "product_type": "{$categoryInfo['product_types']}"
    },
    "reasoning": "Explain which evidence drove this identification and the industry chosen"
  },
  "search_strategy_for_stage2": {
    "primary_search_terms": [],
    "recommended_approach": ""
  }
}

Return ONLY valid JSON.
PROMPT;
    }

    /**
     * Get category-specific expertise information
     */
    protected function getCategoryExpertiseInfo(string $category): array
    {
        $expertiseMap = [
            'electrical_equipment_supplies' => [
                'title' => 'ELECTRICAL EQUIPMENT & SUPPLIES',
                'expert_type' => 'electrical engineer',
                'experience' => '20+ years experience',
                'product_types' => 'Breaker/Contactor/Relay/Transformer/Meter/Other',
            ],
        ];

        return $expertiseMap[$category] ?? [
            'title' => strtoupper(str_replace('_', ' ', $category)),
            'expert_type' => 'technical specialist',
            'experience' => '15+ years experience',
            'product_types' => 'Various',
        ];
    }

    // Method dispatcher for all category prompts
    public function __call($name, $arguments)
    {
        if (strpos($name, 'build') === 0 && strpos($name, 'Prompt') !== false) {
            $category = strtolower(str_replace(['build', 'Prompt'], '', $name));
            $category = preg_replace('/([A-Z])/', '_$1', $category);
            $category = trim($category, '_');

            // No dedicated expert prompt exists for this category — fall back to the
            // hardened generic template. Log it so missing experts stay visible.
            Log::info('No dedicated expert prompt; using hardened generic template', [
                'requested_method' => $name,
                'category' => $category,
            ]);

            return $this->buildRemainingExpertPrompts($category, $arguments[0], $arguments[1]);
        }

        throw new \BadMethodCallException("Method {$name} not found");
    }

    /**
     * Extract JSON from response
     */
    protected function extractJson(string $content): string
    {
        $content = preg_replace('/```json\s*/i', '', $content);
        $content = preg_replace('/```\s*$/i', '', $content);

        return trim($content);
    }
}
