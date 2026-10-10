<?php

namespace App\Services;

use App\Models\Catalog\Product;
use App\Models\Ebay\EbayListing;
use App\Models\Ebay\EbaySyncLog;
use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EbayInventoryService
{
    private const LISTING_DESCRIPTION_MAX_LENGTH = 4000;

    /**
     * eBay rejects any item-specific (aspect) value longer than 65 characters.
     */
    private const EBAY_ASPECT_VALUE_MAX_LENGTH = 65;

    protected IntegrationCredential $credential;

    protected ?OAuthToken $token = null;

    protected string $baseUrl;

    protected string $marketplaceId;

    /**
     * Retryable eBay error codes
     */
    protected array $retryableErrors = [
        25001, // System error
        25002, // Invalid access token
        25008, // Rate limit exceeded
        25019, // Service unavailable
    ];

    public function __construct(IntegrationCredential $credential)
    {
        $this->credential = $credential;
        $this->baseUrl = $credential->getEbayApiUrl();
        $this->marketplaceId = $credential->marketplace_id ?? 'EBAY_US';
    }

    /**
     * Set a specific token to use (optional)
     */
    public function withToken(OAuthToken $token): self
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Get the active OAuth token
     */
    protected function getToken(): OAuthToken
    {
        if ($this->token) {
            return $this->token;
        }

        $this->token = $this->credential->getActiveEbayToken();

        if (! $this->token) {
            throw new Exception('No active eBay OAuth token available. Please authorize the eBay connection.');
        }

        // Refresh if needed
        if ($this->token->needsRefresh()) {
            $oauthService = new EbayOAuthService($this->credential);
            $this->token = $oauthService->refreshToken($this->token);
        }

        return $this->token;
    }

    /**
     * Get default headers for eBay API requests
     */
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->getToken()->decrypted_access_token,
            'Content-Type' => 'application/json',
            'Content-Language' => 'en-US',
            'X-EBAY-C-MARKETPLACE-ID' => $this->marketplaceId,
        ];
    }

    /**
     * Make an API request with logging
     */
    protected function request(
        string $method,
        string $endpoint,
        array $data = [],
        ?EbayListing $listing = null,
        string $operation = 'api_request'
    ): array {
        $url = $this->baseUrl.$endpoint;

        // Start sync log
        $log = EbaySyncLog::startOperation(
            $this->credential->id,
            $this->credential->team_id,
            $operation,
            [
                'ebay_listing_id' => $listing?->id,
                'product_id' => $listing?->product_id,
                'ebay_sku' => $listing?->ebay_sku ?? ($data['sku'] ?? null),
            ]
        );

        $log->setRequest($endpoint, $method, $data);

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->$method($url, $data);

            return $this->handleResponse($response, $log, $listing);

        } catch (EbayApiException $e) {
            // Already logged by handleResponse() — just re-throw
            throw $e;
        } catch (Exception $e) {
            // True connection/network error — log it
            $log->completeError(
                $e->getMessage(),
                'CONNECTION_ERROR',
                ['exception' => get_class($e)],
                null,
                true // retryable
            );

            throw $e;
        }
    }

    /**
     * Handle API response
     */
    protected function handleResponse(Response $response, EbaySyncLog $log, ?EbayListing $listing): array
    {
        $statusCode = $response->status();
        $body = $response->json() ?? [];

        // Success cases: 200, 201, 204
        if ($response->successful()) {
            $log->completeSuccess($body, $statusCode);

            return $body;
        }

        // Extract error details
        $errorMessage = $this->extractErrorMessage($body);
        $errorCode = $this->extractErrorCode($body);
        $isRetryable = $this->isRetryableError($body, $statusCode);

        $log->completeError(
            $errorMessage,
            $errorCode,
            $body,
            $statusCode,
            $isRetryable
        );

        // Update listing status if applicable
        if ($listing) {
            $listing->recordError($errorMessage, $errorCode, $body);
        }

        throw new EbayApiException($errorMessage, $errorCode, $body, $statusCode);
    }

    /**
     * Extract error message from eBay response
     */
    protected function extractErrorMessage(array $body): string
    {
        if (isset($body['errors'][0]['message'])) {
            return $body['errors'][0]['message'];
        }

        if (isset($body['error_description'])) {
            return $body['error_description'];
        }

        if (isset($body['message'])) {
            return $body['message'];
        }

        return 'Unknown eBay API error';
    }

    /**
     * Extract error code from eBay response
     */
    protected function extractErrorCode(array $body): ?string
    {
        if (isset($body['errors'][0]['errorId'])) {
            return (string) $body['errors'][0]['errorId'];
        }

        if (isset($body['error'])) {
            return $body['error'];
        }

        return null;
    }

    /**
     * Check if error is retryable
     */
    protected function isRetryableError(array $body, int $statusCode): bool
    {
        // Server errors are generally retryable
        if ($statusCode >= 500) {
            return true;
        }

        // Rate limiting
        if ($statusCode === 429) {
            return true;
        }

        // Check specific error codes
        if (isset($body['errors'][0]['errorId'])) {
            return in_array((int) $body['errors'][0]['errorId'], $this->retryableErrors);
        }

        return false;
    }

    // =========================================================================
    // INVENTORY ITEM OPERATIONS
    // =========================================================================

    /**
     * Create or update an inventory item
     */
    public function createOrUpdateInventoryItem(EbayListing $listing, array $productData): bool
    {
        $sku = $listing->ebay_sku;

        $inventoryData = $this->buildInventoryItemPayload($productData);

        // Store the payload and aspect audit for debugging
        $listing->update([
            'last_sync_payload' => $inventoryData,
            'aspect_audit' => $this->lastAspectAudit,
        ]);

        if (! empty($this->lastAspectAudit)) {
            Log::warning('Aspect audit issues detected', [
                'sku' => $sku,
                'listing_id' => $listing->id,
                'issues' => $this->lastAspectAudit,
            ]);
        }

        $this->request(
            'put',
            "/sell/inventory/v1/inventory_item/{$sku}",
            $inventoryData,
            $listing,
            EbaySyncLog::OP_CREATE_INVENTORY
        );

        // Update listing status
        $listing->update([
            'status' => EbayListing::STATUS_INVENTORY_CREATED,
            'last_inventory_update_at' => now(),
            'product_data_hash' => $listing->generateProductHash(),
        ]);

        $listing->markSynced();

        return true;
    }

    /**
     * Build inventory item payload from product data
     */
    protected function buildInventoryItemPayload(array $data): array
    {
        $payload = [
            'product' => [
                'title' => mb_substr($data['title'] ?? '', 0, 80),
                'description' => substr($data['description'] ?? $data['title'], 0, self::LISTING_DESCRIPTION_MAX_LENGTH),
            ],
            'condition' => $this->mapCondition($data['condition'] ?? 'NEW'),
            'availability' => [
                'shipToLocationAvailability' => [
                    'quantity' => (int) ($data['quantity'] ?? 1),
                ],
            ],
        ];

        // Only include aspects when non-empty. eBay requires product.aspects to be
        // a JSON object; an empty PHP array serializes as [] and is rejected with
        // "Could not serialize field [product.aspects]".
        $aspects = $this->buildAspects($data);
        if (! empty($aspects)) {
            $payload['product']['aspects'] = $aspects;
        }

        // Add images if provided
        if (! empty($data['images'])) {
            $payload['product']['imageUrls'] = $this->buildImageUrls($data['images']);
        }

        // Add condition description if provided
        if (! empty($data['condition_description'])) {
            $payload['conditionDescription'] = $data['condition_description'];
        }

        // Add package weight and size if provided
        if ($this->hasPackageInfo($data)) {
            $payload['packageWeightAndSize'] = $this->buildPackageInfo($data);
        }

        return $payload;
    }

    /**
     * Build full image URLs for eBay from relative paths
     */
    protected function buildImageUrls(array $images): array
    {
        return collect($images)
            ->map(fn ($path) => Storage::disk('public')->url($path))
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Build product aspects (attributes) for eBay.
     * Stores an aspect audit trail in $this->lastAspectAudit for debugging.
     */
    protected array $lastAspectAudit = [];

    protected function buildAspects(array $data): array
    {
        $aspects = [];
        $this->lastAspectAudit = [];

        // Build an allowed-values index from required_aspects for fuzzy matching
        $allowedValuesIndex = $this->buildAllowedValuesIndex($data['required_aspects'] ?? []);

        // 1. Standard identifiers
        if (! empty($data['brand'])) {
            $aspects['Brand'] = [$this->matchAllowedValue('Brand', $data['brand'], $allowedValuesIndex)];
        }

        if (! empty($data['mpn'])) {
            $aspects['MPN'] = [$data['mpn']];
        }

        if (! empty($data['model'])) {
            $aspects['Model'] = [$data['model']];
        }

        if (! empty($data['upc'])) {
            $aspects['UPC'] = [$data['upc']];
        }

        if (! empty($data['ean'])) {
            $aspects['EAN'] = [$data['ean']];
        }

        // Spec key -> eBay aspect name mapping (safety net for known keys)
        $specMapping = [
            'ram' => 'RAM Size',
            'ram_size' => 'RAM Size',
            'memory' => 'RAM Size',
            'processor' => 'Processor',
            'cpu' => 'Processor',
            'storage' => 'Storage Capacity',
            'storage_capacity' => 'Storage Capacity',
            'ssd' => 'SSD Capacity',
            'ssd_capacity' => 'SSD Capacity',
            'hard_drive' => 'Hard Drive Capacity',
            'screen_size' => 'Screen Size',
            'display_size' => 'Screen Size',
            'operating_system' => 'Operating System',
            'os' => 'Operating System',
            'gpu' => 'GPU',
            'graphics_card' => 'GPU',
            'graphics' => 'GPU',
            'resolution' => 'Maximum Resolution',
            'max_resolution' => 'Maximum Resolution',
            'color' => 'Color',
            'colour' => 'Color',
            'weight' => 'Item Weight',
            'dimensions' => 'Item Dimensions',
            'warranty' => 'Warranty',
            'type' => 'Type',
            'connectivity' => 'Connectivity',
            'form_factor' => 'Form Factor',
            'interface' => 'Interface',
            'voltage' => 'Voltage',
            'storage_type' => 'Storage Type',
            'network' => 'Network',
            'battery_life' => 'Battery Life',
            // Universal / cross-category
            'size' => 'Size',
            'material' => 'Material',
            'style' => 'Style',
            'pattern' => 'Pattern',
            'theme' => 'Theme',
            'gender' => 'Department',
            'department' => 'Department',
            'age_group' => 'Age Group',
            'era' => 'Era',
            'year' => 'Year Manufactured',
            'year_manufactured' => 'Year Manufactured',
            'country_of_manufacture' => 'Country/Region of Manufacture',
            'country_of_origin' => 'Country/Region of Manufacture',
            // Fashion
            'shoe_size' => 'US Shoe Size',
            'us_shoe_size' => 'US Shoe Size',
            'waist_size' => 'Waist Size',
            'inseam' => 'Inseam',
            'sleeve_length' => 'Sleeve Length',
            'closure' => 'Closure',
            'occasion' => 'Occasion',
            'season' => 'Season',
            // Home / Sports / Music
            'room' => 'Room',
            'capacity' => 'Capacity',
            'power_source' => 'Power Source',
            'finish' => 'Finish',
            'features' => 'Features',
            'sport' => 'Sport/Activity',
            'skill_level' => 'Skill Level',
            'number_of_strings' => 'Number of Strings',
            'body_type' => 'Body Type',
            'handedness' => 'Handedness',
        ];

        // 2. Product specifications (parse detailed_specs blob first if present)
        if (! empty($data['specifications']) && is_array($data['specifications'])) {
            $data['specifications'] = $this->parseDetailedSpecsBlob($data['specifications']);

            foreach ($data['specifications'] as $key => $value) {
                if (empty($value) || ! is_string($value)) {
                    continue;
                }
                $aspectName = $specMapping[strtolower($key)] ?? null;
                if ($aspectName && ! isset($aspects[$aspectName])) {
                    $aspects[$aspectName] = [$this->matchAllowedValue($aspectName, $value, $allowedValuesIndex)];
                }
            }
        }

        // 3. Product attributes (same mapping)
        if (! empty($data['attributes']) && is_array($data['attributes'])) {
            foreach ($data['attributes'] as $key => $value) {
                if (empty($value)) {
                    continue;
                }
                $strValue = is_array($value) ? implode(', ', $value) : $value;
                if (! is_string($strValue) || empty($strValue)) {
                    continue;
                }
                $aspectName = $specMapping[strtolower($key)] ?? null;
                if ($aspectName && ! isset($aspects[$aspectName])) {
                    $aspects[$aspectName] = [$this->matchAllowedValue($aspectName, $strValue, $allowedValuesIndex)];
                }
            }
        }

        // 4. Custom aspects from wizard (highest priority -- overwrites)
        if (! empty($data['aspects']) && is_array($data['aspects'])) {
            foreach ($data['aspects'] as $key => $value) {
                if (empty($value)) {
                    continue;
                }
                $strVal = is_array($value) ? $value[0] : $value;
                $aspects[$key] = [$this->matchAllowedValue($key, $strVal, $allowedValuesIndex)];
            }
        }

        // 5. Fill missing required aspects using eBay's category requirements
        if (! empty($data['required_aspects'])) {
            $aspects = $this->fillRequiredAspects($aspects, $data['required_aspects'], $data, $allowedValuesIndex);
        }

        // 6. Final guard: eBay rejects any aspect value > 65 chars. Sanitize every
        //    value from every source (identifiers, specs, attributes, wizard, filled
        //    required). Drop aspects that have no usable value left.
        foreach ($aspects as $name => $values) {
            $clean = [];
            foreach ((array) $values as $value) {
                $sanitized = $this->sanitizeAspectValue((string) $value);
                if ($sanitized !== '') {
                    $clean[] = $sanitized;
                }
            }

            if (empty($clean)) {
                unset($aspects[$name]);
            } else {
                $aspects[$name] = $clean;
            }
        }

        return $aspects;
    }

    /**
     * Make an item-specific value safe for the eBay API.
     *
     * eBay rejects aspect values longer than 65 characters. AI research often emits
     * verbose non-answers like "Not specified (typically low current for ignitors,
     * controlled by furnace board)". We strip the parenthetical explanation first so a
     * required aspect keeps a meaningful short value ("Not specified"), then hard-cap at
     * 65 chars on a word boundary as a final safety net.
     */
    private function sanitizeAspectValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Strip a trailing parenthetical explanation: "Foo (long aside)" -> "Foo".
        $parenPos = mb_strpos($value, ' (');
        if ($parenPos !== false && $parenPos > 0) {
            $value = trim(mb_substr($value, 0, $parenPos));
        }

        if (mb_strlen($value) <= self::EBAY_ASPECT_VALUE_MAX_LENGTH) {
            return $value;
        }

        // Truncate to 65 chars, preferring the last word boundary (no ellipsis -- eBay
        // counts characters, so the cap must hold even after trimming).
        $truncated = mb_substr($value, 0, self::EBAY_ASPECT_VALUE_MAX_LENGTH);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace >= 40) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return trim($truncated);
    }

    /**
     * Parse a "detailed_specs" blob string into individual key-value pairs.
     * Handles formats like "Processor: Intel Core i7-720QM; Display: 17.3-inch"
     * and merges them back into the specifications array.
     */
    private function parseDetailedSpecsBlob(array $specifications): array
    {
        if (empty($specifications['detailed_specs']) || ! is_string($specifications['detailed_specs'])) {
            return $specifications;
        }

        $blob = $specifications['detailed_specs'];
        unset($specifications['detailed_specs']);

        // Split on semicolons, then parse "Key: Value" pairs
        $segments = preg_split('/\s*;\s*/', $blob);
        foreach ($segments as $segment) {
            $segment = trim($segment);
            if (empty($segment)) {
                continue;
            }

            // Split on first colon only
            $colonPos = strpos($segment, ':');
            if ($colonPos === false) {
                continue;
            }

            $key = trim(substr($segment, 0, $colonPos));
            $value = trim(substr($segment, $colonPos + 1));

            if (! empty($key) && ! empty($value)) {
                // Only add if not already present (don't overwrite structured keys)
                if (! isset($specifications[$key])) {
                    $specifications[$key] = $value;
                }
            }
        }

        return $specifications;
    }

    /**
     * Build an index of allowed values per aspect name for fast lookup.
     * Returns: ['Aspect Name' => ['mode' => 'SELECTION_ONLY', 'values' => [...]], ...]
     */
    private function buildAllowedValuesIndex(array $requiredAspects): array
    {
        $index = [];
        foreach ($requiredAspects as $aspect) {
            $name = $aspect['name'] ?? '';
            if (empty($name)) {
                continue;
            }
            $index[$name] = [
                'mode' => $aspect['mode'] ?? 'FREE_TEXT',
                'values' => $aspect['allowed_values'] ?? [],
            ];
        }

        return $index;
    }

    /**
     * Fuzzy-match a value against eBay's allowed values for an aspect.
     * Returns the best matching allowed value, or the original value if no match/free text.
     */
    private function matchAllowedValue(string $aspectName, string $value, array $allowedValuesIndex): string
    {
        if (empty($value) || ! isset($allowedValuesIndex[$aspectName])) {
            return $value;
        }

        $allowed = $allowedValuesIndex[$aspectName]['values'] ?? [];
        if (empty($allowed)) {
            return $value;
        }

        // Exact match (case-insensitive)
        foreach ($allowed as $av) {
            if (strcasecmp($av, $value) === 0) {
                return $av;
            }
        }

        // Normalized match (strip spaces, dashes, dots)
        $normalizedValue = strtolower(preg_replace('/[\s\-\.]+/', '', $value));
        foreach ($allowed as $av) {
            $normalizedAv = strtolower(preg_replace('/[\s\-\.]+/', '', $av));
            if ($normalizedAv === $normalizedValue) {
                return $av;
            }
        }

        // Substring containment (value contains allowed or vice versa)
        $lowerValue = strtolower($value);
        foreach ($allowed as $av) {
            $lowerAv = strtolower($av);
            if (str_contains($lowerValue, $lowerAv) || str_contains($lowerAv, $lowerValue)) {
                return $av;
            }
        }

        // For SELECTION_ONLY aspects, log the mismatch but still send the value
        // (eBay may reject it, but that's better than "Does Not Apply")
        $mode = $allowedValuesIndex[$aspectName]['mode'] ?? 'FREE_TEXT';
        if ($mode === 'SELECTION_ONLY') {
            $this->lastAspectAudit[] = [
                'aspect' => $aspectName,
                'provided_value' => $value,
                'status' => 'no_match',
                'reason' => 'Value does not match any of '.count($allowed).' allowed options',
                'sample_allowed' => array_slice($allowed, 0, 5),
            ];
        }

        return $value;
    }

    /**
     * Fill missing required aspects using multiple strategies:
     * A) Spec/attribute key lookup, B) Title extraction, C) Universal defaults, D) "Does Not Apply"
     */
    private function fillRequiredAspects(array $aspects, array $requiredSpecifics, array $data, array $allowedValuesIndex = []): array
    {
        $universalDefaults = [
            'country/region of manufacture' => 'United States',
            'modified item' => 'No',
            'custom bundle' => 'No',
            'bundled items' => 'None',
            'non-domestic product' => 'No',
        ];

        $identifierDefaults = [
            'mpn' => fn ($d) => empty($d['mpn']) ? 'Does Not Apply' : null,
            'upc' => fn ($d) => empty($d['upc']) ? 'Does Not Apply' : null,
            'ean' => fn ($d) => empty($d['ean']) ? 'Does Not Apply' : null,
        ];

        foreach ($requiredSpecifics as $specific) {
            $name = $specific['name'] ?? '';
            $cardinality = $specific['cardinality'] ?? '';

            if (empty($name) || $cardinality !== 'REQUIRED' || isset($aspects[$name])) {
                continue;
            }

            $lower = strtolower($name);

            // Strategy A: look for the aspect name as a spec/attribute key (case-insensitive)
            $value = $this->findValueInSources($name, $data);

            // Strategy B: title extraction for common aspects
            if (empty($value)) {
                $value = $this->extractAspectFromTitle($name, $data['title'] ?? '');
            }

            // Strategy C: universal defaults
            if (empty($value)) {
                $value = $universalDefaults[$lower] ?? null;
            }

            // Strategy C2: identifier defaults
            if (empty($value) && isset($identifierDefaults[$lower])) {
                $value = ($identifierDefaults[$lower])($data);
            }

            // Strategy D: "Does Not Apply" as final fallback for required aspects
            if (empty($value)) {
                $value = 'Does Not Apply';
                $this->lastAspectAudit[] = [
                    'aspect' => $name,
                    'provided_value' => 'Does Not Apply',
                    'status' => 'defaulted',
                    'reason' => 'No value found from any source (specs, attributes, title, defaults)',
                ];
            }

            $aspects[$name] = [$this->matchAllowedValue($name, $value, $allowedValuesIndex)];
        }

        return $aspects;
    }

    /**
     * Search for a value across specs, attributes, and aspects using case-insensitive matching.
     */
    private function findValueInSources(string $aspectName, array $data): ?string
    {
        $lower = strtolower($aspectName);
        $sources = ['specifications', 'attributes', 'aspects'];

        foreach ($sources as $source) {
            if (empty($data[$source]) || ! is_array($data[$source])) {
                continue;
            }

            // Exact key match
            if (! empty($data[$source][$aspectName])) {
                $val = $data[$source][$aspectName];

                return is_array($val) ? ($val[0] ?? null) : (string) $val;
            }

            // Lowercase key match
            if (! empty($data[$source][$lower])) {
                $val = $data[$source][$lower];

                return is_array($val) ? ($val[0] ?? null) : (string) $val;
            }

            // Case-insensitive scan
            foreach ($data[$source] as $key => $val) {
                if (strcasecmp($key, $aspectName) === 0 && ! empty($val)) {
                    return is_array($val) ? ($val[0] ?? null) : (string) $val;
                }
            }
        }

        // Final strategy: search for "AspectName: value" inside any detailed_specs blob strings
        foreach ($sources as $source) {
            if (empty($data[$source]) || ! is_array($data[$source])) {
                continue;
            }

            foreach ($data[$source] as $val) {
                if (! is_string($val) || strlen($val) < 10) {
                    continue;
                }

                // Look for "AspectName: value" pattern in the blob
                $pattern = '/'.preg_quote($aspectName, '/').'\s*:\s*([^;]+)/i';
                if (preg_match($pattern, $val, $m)) {
                    $extracted = trim($m[1]);
                    if (! empty($extracted)) {
                        return $extracted;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract aspect values commonly found in product titles
     */
    private function extractAspectFromTitle(string $aspectName, string $title): ?string
    {
        if (empty($title)) {
            return null;
        }

        $lower = strtolower($aspectName);

        return match (true) {
            str_contains($lower, 'processor') || $lower === 'cpu' => $this->matchFirst($title, [
                '/\b(Apple\s+M[1-4](?:\s+(?:Pro|Max|Ultra|Nano))?)\b/i',
                '/\b(M[1-4](?:\s+(?:Pro|Max|Ultra))?)\s+(?:chip|processor)\b/i',
                '/\b(Intel\s+Core\s+(?:Ultra\s+)?i[3579]-\d{4,5}[A-Z]*)\b/i',
                '/\b(Intel\s+Core\s+(?:Ultra\s+)?i[3579](?:\s+\d{1,2}th\s+Gen)?)\b/i',
                '/\b(Intel\s+(?:Celeron|Pentium|Xeon|Atom)\s+[A-Z0-9\-]+)\b/i',
                '/\b(AMD\s+Ryzen\s+[3579](?:\s+\d{4}[A-Z]*)?(?:\s+(?:PRO|XT|X|G))?)\b/i',
                '/\b(AMD\s+(?:EPYC|Athlon)\s+[A-Z0-9\-]+)\b/i',
                '/\b(Qualcomm\s+Snapdragon\s+[\w]+)\b/i',
                '/\b(Core\s+(?:Ultra\s+)?i[3579]-\d{4,5}[A-Z]*)\b/i',
                '/\b(i[3579]-\d{4,5}[A-Z]*)\b/i',
            ]),

            str_contains($lower, 'ram') || str_contains($lower, 'memory') => $this->matchFirst($title, [
                '/\b(\d+\s*GB)\s+(?:RAM|Memory|LPDDR\w*)\b/i',
                '/\b(\d+\s*GB)\s+(?:Unified\s+Memory)\b/i',
            ]),

            str_contains($lower, 'storage') || str_contains($lower, 'ssd') || str_contains($lower, 'hard drive') => $this->matchFirst($title, [
                '/\b(\d+\s*(?:GB|TB))\s+(?:SSD|NVMe|HDD|Hard\s+Drive|eMMC|Flash)\b/i',
            ]),

            str_contains($lower, 'screen') || str_contains($lower, 'display') => $this->matchFirst($title, [
                '/\b(\d{1,2}(?:\.\d)?\s*(?:inch|in|"))\b/i',
                '/\b(\d{1,2}(?:\.\d)?)-inch\b/i',
                '/\b(\d{1,2}\.\d)\s*(?:laptop|monitor|display|screen|tablet)\b/i',
            ]),

            str_contains($lower, 'operating system') || $lower === 'os' => $this->matchFirst($title, [
                '/\b(Windows\s+(?:11|10|8|7)(?:\s+(?:Home|Pro|S|SE))?)\b/i',
                '/\b(macOS|Mac\s+OS\s+X?)\b/i',
                '/\b(Chrome\s+OS)\b/i',
                '/\b(Ubuntu|Linux)\b/i',
            ]),

            default => null,
        };
    }

    /**
     * Return the first regex match from a list of patterns
     */
    private function matchFirst(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /**
     * Map internal condition to eBay condition enum
     */
    protected function mapCondition(string $condition): string
    {
        $mapping = [
            'NEW' => 'NEW',
            'NEW_OTHER' => 'NEW_OTHER',
            'NEW_WITH_DEFECTS' => 'NEW_WITH_DEFECTS',
            'MANUFACTURER_REFURBISHED' => 'MANUFACTURER_REFURBISHED',
            'CERTIFIED_REFURBISHED' => 'CERTIFIED_REFURBISHED',
            'USED_EXCELLENT' => 'USED_EXCELLENT',
            'USED_VERY_GOOD' => 'USED_VERY_GOOD',
            'USED_GOOD' => 'USED_GOOD',
            'USED_ACCEPTABLE' => 'USED_ACCEPTABLE',
            'FOR_PARTS_OR_NOT_WORKING' => 'FOR_PARTS_OR_NOT_WORKING',
        ];

        return $mapping[strtoupper($condition)] ?? 'NEW';
    }

    /**
     * Check if package info is available
     */
    protected function hasPackageInfo(array $data): bool
    {
        return ! empty($data['weight_lbs']) ||
               (! empty($data['length']) && ! empty($data['width']) && ! empty($data['height']));
    }

    /**
     * Build package weight and size payload
     */
    protected function buildPackageInfo(array $data): array
    {
        $package = [];

        if (! empty($data['length']) && ! empty($data['width']) && ! empty($data['height'])) {
            $package['dimensions'] = [
                'length' => (float) $data['length'],
                'width' => (float) $data['width'],
                'height' => (float) $data['height'],
                'unit' => 'INCH',
            ];
        }

        if (! empty($data['weight_lbs'])) {
            $package['weight'] = [
                'value' => (float) $data['weight_lbs'],
                'unit' => 'POUND',
            ];
        }

        return $package;
    }

    /**
     * Get an inventory item
     */
    public function getInventoryItem(string $sku): ?array
    {
        try {
            return $this->request(
                'get',
                "/sell/inventory/v1/inventory_item/{$sku}",
                [],
                null,
                EbaySyncLog::OP_GET_LISTING
            );
        } catch (EbayApiException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Delete an inventory item
     */
    public function deleteInventoryItem(EbayListing $listing): bool
    {
        $this->request(
            'delete',
            "/sell/inventory/v1/inventory_item/{$listing->ebay_sku}",
            [],
            $listing,
            EbaySyncLog::OP_DELETE_INVENTORY
        );

        $listing->update(['status' => EbayListing::STATUS_DELETED]);

        return true;
    }

    /**
     * Update inventory quantity only
     */
    public function updateQuantity(EbayListing $listing, int $quantity): bool
    {
        $data = [
            'availability' => [
                'shipToLocationAvailability' => [
                    'quantity' => $quantity,
                ],
            ],
        ];

        $this->request(
            'put',
            "/sell/inventory/v1/inventory_item/{$listing->ebay_sku}",
            $data,
            $listing,
            EbaySyncLog::OP_SYNC_QUANTITY
        );

        $listing->update([
            'available_quantity' => $quantity,
            'last_inventory_update_at' => now(),
            'status' => $quantity > 0 ? $listing->status : EbayListing::STATUS_OUT_OF_STOCK,
        ]);

        return true;
    }

    // =========================================================================
    // OFFER OPERATIONS
    // =========================================================================

    /**
     * Create an offer for an inventory item
     *
     * @param  array  $policyIds  Optional array with 'fulfillment', 'payment', 'return' keys for policy IDs
     */
    public function createOffer(EbayListing $listing, array $offerData, array $policyIds = []): string
    {
        $payload = $this->buildOfferPayload($listing, $offerData, $policyIds);

        $response = $this->request(
            'post',
            '/sell/inventory/v1/offer',
            $payload,
            $listing,
            EbaySyncLog::OP_CREATE_OFFER
        );

        $offerId = $response['offerId'] ?? null;

        if (! $offerId) {
            throw new Exception('No offer ID returned from eBay');
        }

        $listing->update([
            'ebay_offer_id' => $offerId,
            'status' => EbayListing::STATUS_OFFER_CREATED,
            'ebay_category_id' => $offerData['categoryId'] ?? null,
            'listed_price' => $offerData['price'] ?? null,
            'listed_quantity' => $offerData['quantity'] ?? null,
            'listing_format' => $offerData['format'] ?? 'FIXED_PRICE',
        ]);

        return $offerId;
    }

    /**
     * Build offer payload
     *
     * @param  array  $policyIds  Optional array with 'fulfillment', 'payment', 'return' keys for policy IDs
     */
    protected function buildOfferPayload(EbayListing $listing, array $data, array $policyIds = []): array
    {
        $payload = [
            'sku' => $listing->ebay_sku,
            'marketplaceId' => $this->marketplaceId,
            'format' => $data['format'] ?? 'FIXED_PRICE',
            'availableQuantity' => (int) ($data['quantity'] ?? 1),
            'categoryId' => $data['categoryId'],
            'merchantLocationKey' => $this->credential->merchant_location_key,
            'pricingSummary' => [
                'price' => [
                    'value' => (string) $data['price'],
                    'currency' => $data['currency'] ?? 'USD',
                ],
            ],
        ];

        // Add listing description if different from inventory description
        if (! empty($data['description'])) {
            $payload['listingDescription'] = substr($data['description'], 0, self::LISTING_DESCRIPTION_MAX_LENGTH);
        }

        // Add business policies - use provided policy IDs or fall back to credential defaults
        $payload['listingPolicies'] = [
            'fulfillmentPolicyId' => $policyIds['fulfillment'] ?? $this->credential->fulfillment_policy_id,
            'paymentPolicyId' => $policyIds['payment'] ?? $this->credential->payment_policy_id,
            'returnPolicyId' => $policyIds['return'] ?? $this->credential->return_policy_id,
        ];

        return $payload;
    }

    /**
     * Update an existing offer
     *
     * @param  array  $policyIds  Optional array with 'fulfillment', 'payment', 'return' keys for policy IDs
     */
    public function updateOffer(EbayListing $listing, array $offerData, array $policyIds = []): bool
    {
        if (! $listing->ebay_offer_id) {
            throw new Exception('No offer ID available for update');
        }

        $payload = $this->buildOfferPayload($listing, $offerData, $policyIds);

        $this->request(
            'put',
            "/sell/inventory/v1/offer/{$listing->ebay_offer_id}",
            $payload,
            $listing,
            EbaySyncLog::OP_UPDATE_OFFER
        );

        $listing->update([
            'listed_price' => $offerData['price'] ?? $listing->listed_price,
            'listed_quantity' => $offerData['quantity'] ?? $listing->listed_quantity,
            'available_quantity' => $offerData['quantity'] ?? $listing->available_quantity,
            'last_price_update_at' => now(),
            'last_inventory_update_at' => now(),
            'last_synced_at' => now(),
        ]);

        return true;
    }

    /**
     * Get an offer by ID
     */
    public function getOffer(string $offerId): ?array
    {
        try {
            return $this->request(
                'get',
                "/sell/inventory/v1/offer/{$offerId}",
                [],
                null,
                EbaySyncLog::OP_GET_LISTING
            );
        } catch (EbayApiException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Delete an offer
     */
    public function deleteOffer(EbayListing $listing): bool
    {
        if (! $listing->ebay_offer_id) {
            return true;
        }

        $this->request(
            'delete',
            "/sell/inventory/v1/offer/{$listing->ebay_offer_id}",
            [],
            $listing,
            EbaySyncLog::OP_DELETE_OFFER
        );

        $listing->update([
            'ebay_offer_id' => null,
            'status' => EbayListing::STATUS_INVENTORY_CREATED,
        ]);

        return true;
    }

    // =========================================================================
    // PUBLISH OPERATIONS
    // =========================================================================

    /**
     * Publish an offer to create a live eBay listing
     */
    public function publishOffer(EbayListing $listing): string
    {
        if (! $listing->ebay_offer_id) {
            throw new Exception('No offer ID available to publish');
        }

        $response = $this->request(
            'post',
            "/sell/inventory/v1/offer/{$listing->ebay_offer_id}/publish",
            [],
            $listing,
            EbaySyncLog::OP_PUBLISH
        );

        $listingId = $response['listingId'] ?? null;

        if (! $listingId) {
            throw new Exception('No listing ID returned from eBay');
        }

        // Build the eBay URL
        $ebayUrl = $this->credential->environment === 'sandbox'
            ? "https://sandbox.ebay.com/itm/{$listingId}"
            : "https://www.ebay.com/itm/{$listingId}";

        $listing->update([
            'ebay_listing_id' => $listingId,
            'ebay_listing_url' => $ebayUrl,
            'status' => EbayListing::STATUS_PUBLISHED,
            'listing_start_date' => now(),
            'consecutive_errors' => 0,
        ]);

        $listing->markSynced();

        Log::info('eBay listing published', [
            'listing_id' => $listing->id,
            'ebay_listing_id' => $listingId,
            'ebay_url' => $ebayUrl,
        ]);

        return $listingId;
    }

    /**
     * End a live listing
     */
    public function endListing(EbayListing $listing, string $reason = 'NOT_AVAILABLE'): bool
    {
        if (! $listing->ebay_offer_id) {
            throw new Exception('No offer ID available to end');
        }

        $this->request(
            'post',
            "/sell/inventory/v1/offer/{$listing->ebay_offer_id}/withdraw",
            [],
            $listing,
            EbaySyncLog::OP_END_LISTING
        );

        $listing->update([
            'status' => EbayListing::STATUS_ENDED,
            'listing_end_date' => now(),
        ]);

        return true;
    }

    // =========================================================================
    // LOCATION OPERATIONS
    // =========================================================================

    /**
     * Create a merchant location
     */
    public function createLocation(string $locationKey, array $locationData): bool
    {
        $log = EbaySyncLog::startOperation(
            $this->credential->id,
            $this->credential->team_id,
            EbaySyncLog::OP_CREATE_LOCATION
        );

        // Normalize country to ISO 2-letter code (eBay requires this format)
        $country = strtoupper(trim($locationData['country'] ?? ''));
        $countryCode = match ($country) {
            'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA', '' => 'US',
            'CANADA', 'CAN' => 'CA',
            'UNITED KINGDOM', 'UK', 'GBR', 'GREAT BRITAIN' => 'GB',
            default => strlen($country) === 2 ? $country : 'US',
        };

        $payload = [
            'location' => [
                'address' => [
                    'addressLine1' => $locationData['address_line1'],
                    'city' => $locationData['city'],
                    'stateOrProvince' => $locationData['state'],
                    'postalCode' => $locationData['postal_code'],
                    'country' => $countryCode,
                ],
            ],
            'name' => $locationData['name'] ?? 'Primary Warehouse',
            'merchantLocationStatus' => 'ENABLED',
            'locationTypes' => ['WAREHOUSE'],
        ];

        if (! empty($locationData['address_line2'])) {
            $payload['location']['address']['addressLine2'] = $locationData['address_line2'];
        }

        $log->setRequest("/sell/inventory/v1/location/{$locationKey}", 'POST', $payload);

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->post("{$this->baseUrl}/sell/inventory/v1/location/{$locationKey}", $payload);

            if ($response->successful()) {
                $log->completeSuccess([], $response->status());

                // Save location key to credential
                $this->credential->update(['merchant_location_key' => $locationKey]);

                return true;
            }

            throw new EbayApiException(
                $this->extractErrorMessage($response->json() ?? []),
                $this->extractErrorCode($response->json() ?? []),
                $response->json() ?? [],
                $response->status()
            );

        } catch (Exception $e) {
            $log->completeError($e->getMessage(), null, [], null, true);
            throw $e;
        }
    }

    /**
     * Get a merchant location
     */
    public function getLocation(string $locationKey): ?array
    {
        try {
            return $this->request(
                'get',
                "/sell/inventory/v1/location/{$locationKey}",
                [],
                null,
                'get_location'
            );
        } catch (EbayApiException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    // =========================================================================
    // BUSINESS POLICY OPERATIONS
    // =========================================================================

    /**
     * Get all business policies
     */
    public function getBusinessPolicies(): array
    {
        $log = EbaySyncLog::startOperation(
            $this->credential->id,
            $this->credential->team_id,
            EbaySyncLog::OP_GET_POLICIES
        );

        try {
            $fulfillment = $this->getFulfillmentPolicies();
            $payment = $this->getPaymentPolicies();
            $return = $this->getReturnPolicies();

            $log->completeSuccess([
                'fulfillment_count' => count($fulfillment),
                'payment_count' => count($payment),
                'return_count' => count($return),
            ]);

            return [
                'fulfillment' => $fulfillment,
                'payment' => $payment,
                'return' => $return,
            ];

        } catch (Exception $e) {
            $log->completeError($e->getMessage());
            throw $e;
        }
    }

    /**
     * Get fulfillment (shipping) policies
     */
    public function getFulfillmentPolicies(): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/sell/account/v1/fulfillment_policy", [
                'marketplace_id' => $this->marketplaceId,
            ]);

        if ($response->successful()) {
            return $response->json('fulfillmentPolicies', []);
        }

        return [];
    }

    /**
     * Get payment policies
     */
    public function getPaymentPolicies(): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/sell/account/v1/payment_policy", [
                'marketplace_id' => $this->marketplaceId,
            ]);

        if ($response->successful()) {
            return $response->json('paymentPolicies', []);
        }

        return [];
    }

    /**
     * Get return policies
     */
    public function getReturnPolicies(): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/sell/account/v1/return_policy", [
                'marketplace_id' => $this->marketplaceId,
            ]);

        if ($response->successful()) {
            return $response->json('returnPolicies', []);
        }

        return [];
    }

    /**
     * Save business policy IDs to credential
     */
    public function savePolicyIds(string $fulfillmentId, string $paymentId, string $returnId): void
    {
        $this->credential->update([
            'fulfillment_policy_id' => $fulfillmentId,
            'payment_policy_id' => $paymentId,
            'return_policy_id' => $returnId,
        ]);
    }

    // =========================================================================
    // CATEGORY OPERATIONS
    // =========================================================================

    /**
     * Get category suggestions for a product
     */
    public function getCategorySuggestions(string $query): array
    {
        try {
            $token = $this->getAppToken();

            $response = Http::withToken($token)
                ->timeout(15)
                ->get($this->baseUrl.'/commerce/taxonomy/v1/category_tree/0/get_category_suggestions', [
                    'q' => $query,
                ]);

            if ($response->successful()) {
                return $response->json('categorySuggestions', []);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get category suggestions', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Get category details
     */
    public function getCategory(string $categoryId): ?array
    {
        try {
            $token = $this->getAppToken();

            $response = Http::withToken($token)
                ->timeout(15)
                ->get($this->baseUrl.'/commerce/taxonomy/v1/category_tree/0/get_category_subtree', [
                    'category_id' => $categoryId,
                ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get category details', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Get an app-level OAuth token for public APIs (Taxonomy, etc.)
     */
    protected function getAppToken(): string
    {
        $cacheKey = 'ebay_app_token_inventory';

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 60 * 110, function () {
            $config = app(EbayConfigService::class);

            $response = Http::asForm()
                ->withBasicAuth($config->getClientId(), $config->getClientSecret())
                ->post($config->getApiBaseUrl().'/identity/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                    'scope' => 'https://api.ebay.com/oauth/api_scope',
                ]);

            if (! $response->successful()) {
                throw new Exception('Failed to get eBay app token: '.$response->body());
            }

            return $response->json('access_token');
        });
    }

    // =========================================================================
    // HIGH-LEVEL SYNC OPERATIONS
    // =========================================================================

    /**
     * Full sync: Create inventory, offer, and publish
     *
     * @param  array  $policyIds  Optional array with 'fulfillment', 'payment', 'return' keys for policy IDs
     */
    public function fullSync(EbayListing $listing, array $productData, array $offerData, array $policyIds = []): string
    {
        // Validate credential is ready
        if (! $this->credential->isEbayReady()) {
            throw new Exception('eBay credential is not fully configured. Missing policies or location.');
        }

        // Step 1: Create/Update Inventory Item
        $this->createOrUpdateInventoryItem($listing, $productData);

        // Step 2: Create Offer (with optional policy overrides)
        if (! $listing->ebay_offer_id) {
            $this->createOffer($listing, $offerData, $policyIds);
        } else {
            $this->updateOffer($listing, $offerData, $policyIds);
        }

        // Step 3: Publish
        return $this->publishOffer($listing);
    }

    /**
     * Sync a product from the Product model
     */
    public function syncProduct(Product $product, ?string $categoryId = null): EbayListing
    {
        // Get or create the listing record
        $listing = EbayListing::firstOrCreate(
            [
                'product_id' => $product->id,
                'integration_credential_id' => $this->credential->id,
            ],
            [
                'team_id' => $this->credential->team_id,
                'ebay_sku' => $product->generateEbaySku(),
                'status' => EbayListing::STATUS_PENDING,
            ]
        );

        // Get product data formatted for eBay
        $productData = $product->toEbayInventoryData();

        // Build offer data
        $offerData = [
            'categoryId' => $categoryId ?? $this->findBestCategory($product),
            'price' => $product->selling_price,
            'quantity' => $product->available_quantity ?? $product->stock_quantity,
            'currency' => $product->currency ?? 'USD',
            'description' => $product->description,
            'format' => 'FIXED_PRICE',
        ];

        // Run full sync
        $this->fullSync($listing, $productData, $offerData);

        return $listing->fresh();
    }

    /**
     * Find the best eBay category for a product
     */
    protected function findBestCategory(Product $product): string
    {
        $searchTerms = [];

        if ($product->brand) {
            $searchTerms[] = $product->brand->name;
        }

        if ($product->category) {
            $searchTerms[] = $product->category->name;
        }

        $searchTerms[] = $product->name;

        $query = implode(' ', array_slice($searchTerms, 0, 3));
        $suggestions = $this->getCategorySuggestions($query);

        if (! empty($suggestions[0]['category']['categoryId'])) {
            return $suggestions[0]['category']['categoryId'];
        }

        throw new Exception("Could not find an eBay category for product: {$product->name}");
    }
}
