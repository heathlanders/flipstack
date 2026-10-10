<?php

namespace App\Services;

class EbaySkuLocationParser
{
    /**
     * Special area keywords (case-insensitive match).
     */
    private const SPECIAL_AREAS = ['FOH', 'CART', 'MEZZ', 'MEZZANINE', 'RACK', 'OFFICE'];

    /**
     * Owner prefixes that should NEVER be tagged as consignors when they appear
     * as a non-first token. Configured per org via IntegrationCredential.config
     * but seeded with common real-world owner codes so out-of-the-box
     * behaviour is sane.
     *
     * @var array<int,string>
     */
    private array $knownOwners;

    /**
     * Employee/lister initials that should be tagged as `lister_initials` rather
     * than `consignor`. Real consignors are 3rd-party suppliers (and become
     * Supplier rows); listers are staff who entered the product (no Supplier).
     *
     * @var array<int,string>
     */
    private array $knownListers;

    /**
     * @param  array<int,string>  $knownOwners  Owner codes that are never consignors. Case-insensitive.
     * @param  array<int,string>  $knownListers  Staff initials that route to `lister_initials` instead of `consignor`. Case-insensitive.
     */
    public function __construct(array $knownOwners = [], array $knownListers = [])
    {
        $this->knownOwners = array_values(array_unique(array_map('strtoupper', $knownOwners)));
        $this->knownListers = array_values(array_unique(array_map('strtoupper', $knownListers)));
    }

    /**
     * Parse a raw eBay custom SKU string into structured location data.
     *
     * @return array{
     *     owner: ?string,
     *     consignor: ?string,
     *     lister_initials: ?string,
     *     bracket_prefix: ?string,
     *     item_id: ?string,
     *     product_sku: ?string,
     *     intake_status: ?string,
     *     room: ?string,
     *     shelf: ?string,
     *     container_type: ?string,
     *     container_name: ?string,
     *     level: ?string,
     *     special_area: ?string,
     *     raw_location: ?string,
     * }
     */
    public function parse(string $rawSku): array
    {
        $rawSku = trim($rawSku);

        $result = [
            'owner' => null,
            'consignor' => null,
            'lister_initials' => null,
            'bracket_prefix' => null,
            'item_id' => null,
            'product_sku' => null,
            'intake_status' => null,
            'room' => null,
            'shelf' => null,
            'container_type' => null,
            'container_name' => null,
            'level' => null,
            'special_area' => null,
            'raw_location' => null,
        ];

        if ($rawSku === '') {
            return $result;
        }

        // Capture leading bracket-prefix markers like "(C)", "(T)", "(COOP)",
        // "(GHI)". Letter meaning is org-specific (condition / consignment flag)
        // — we persist the content without interpreting it.
        if (preg_match('/^\((\w{1,4})\)\s*-?\s*/', $rawSku, $bm)) {
            $result['bracket_prefix'] = strtoupper($bm[1]);
            $rawSku = substr($rawSku, strlen($bm[0]));
        }

        // Extract owner (first word: letters + optional digit). Case-insensitive
        // so lowercase typos like `arc abc ...` still parse; uppercase the result
        // so downstream comparisons against known_owners/known_listers hold.
        // Skip if the first token is a location keyword (SHELF/RM/TUB/BOX/...) —
        // those are unowned location-first SKUs that should fall through to
        // keyword extraction directly.
        if (preg_match('/^([A-Za-z][A-Za-z0-9]{1,5})\s+/', $rawSku, $m)
            && ! $this->isLocationKeyword(strtoupper($m[1]))
        ) {
            $result['owner'] = strtoupper($m[1]);
            $remaining = substr($rawSku, strlen($m[0]));
        } else {
            // No recognizable owner prefix
            $result['raw_location'] = $rawSku;

            return $this->extractLocationKeywords($result, $rawSku);
        }

        $result['raw_location'] = $remaining;

        // Detect ARC pattern: ARC [consignor] PRODUCT-SKU ...
        if ($result['owner'] === 'ARC') {
            return $this->parseArcSku($result, $remaining);
        }

        return $this->parseGenericSku($result, $remaining);
    }

    /**
     * Parse ARC-pattern SKU: ARC [consignor_initials] PRODUCT-SKU [(xN)] LOCATION_TOKENS
     *
     * ARC SKUs have a real product identifier (dash-delimited like IND-18979-9 or PO-1033-1022-312).
     */
    private function parseArcSku(array $result, string $remaining): array
    {
        // ARC PENDING / ID PENDING / PO PENDING marker — these are not-yet-
        // intaken items. Tag the product and skip consignor + product_sku
        // detection (there is no real consignor or SKU yet). Best-effort
        // location extraction still runs on the remainder.
        if (preg_match('/^[\s\-\/]*(?:ID|PO)\s+PENDING\b/i', $remaining)) {
            $result['intake_status'] = 'pending';
            // Strip just the "ID PENDING" / "PO PENDING" marker. Don't try to
            // gobble whatever placeholder might follow (e.g. "7776MC12") — if
            // it's not a location keyword, extractLocationKeywords will skip
            // it harmlessly. Conservatively stripping more risks eating real
            // location keywords like "RM" in "ID PENDING RM 132 Shelf 7".
            $cleaned = preg_replace(
                '/[\s\-\/]*(?:ID|PO)\s+PENDING\s*/i',
                ' ',
                $remaining
            );

            return $this->extractLocationKeywords($result, $cleaned);
        }

        $tokens = preg_split('/\s+/', $remaining);
        $consumed = [];

        // Check for staff lister initials first (configured via known_listers).
        // Listers must not become Suppliers. Fall through to consignor detection
        // if the token is not in the lister list.
        if (! empty($tokens[0]) && $this->isListerCode($tokens[0])) {
            $result['lister_initials'] = strtoupper($tokens[0]);
            $consumed[] = 0;
        } elseif (! empty($tokens[0]) && $this->isConsignorCode($tokens[0])) {
            $result['consignor'] = strtoupper($tokens[0]);
            $consumed[] = 0;
        }

        // Find the product SKU: a token with dashes that looks like a product ID
        // Patterns: IND-18979-9, PO-1033-1022-312, CS-1043-1020-102, ELEC-71905-532, G73JH-X1
        foreach ($tokens as $i => $token) {
            if (in_array($i, $consumed)) {
                continue;
            }
            if ($this->isProductSku($token)) {
                $result['product_sku'] = $token;
                $consumed[] = $i;
                break;
            }
        }

        // Remove consumed tokens and extract location keywords from the rest
        $locationTokens = [];
        foreach ($tokens as $i => $token) {
            if (! in_array($i, $consumed)) {
                $locationTokens[] = $token;
            }
        }

        $locationString = implode(' ', $locationTokens);

        return $this->extractLocationKeywords($result, $locationString);
    }

    /**
     * Parse non-ARC SKU: OWNER [consignor item_id] [price] LOCATION_KEYWORDS
     */
    private function parseGenericSku(array $result, string $remaining): array
    {
        $tokens = preg_split('/\s+/', $remaining);
        $consumed = [];

        // Check for staff lister initials first (configured via known_listers).
        // Listers must not become Suppliers and never carry an item_id; if they
        // do, the token is more likely a real consignor instead.
        if (! empty($tokens[0]) && $this->isListerCode($tokens[0])) {
            $result['lister_initials'] = strtoupper($tokens[0]);
            $consumed[] = 0;
        } elseif (! empty($tokens[0]) && $this->isConsignorCode($tokens[0])) {
            $result['consignor'] = strtoupper($tokens[0]);
            $consumed[] = 0;

            // Check for 5-digit item ID after consignor
            if (isset($tokens[1]) && preg_match('/^\d{4,6}$/', $tokens[1])) {
                $result['item_id'] = $tokens[1];
                $consumed[] = 1;
            }
        }

        // Check for price tag (KELP pattern). Must contain an actual `$` sign
        // so we don't eat item IDs that look like decimals (e.g. "33.15731").
        $priceIndex = null;
        foreach ($tokens as $i => $token) {
            if (in_array($i, $consumed)) {
                continue;
            }
            if (! str_contains($token, '$')) {
                continue;
            }
            // Accept "$3.44", "$14", "3.44$", ".50$" — anything with a $ and digits.
            if (preg_match('/^\$?\d+(\.\d{1,2})?\$?$/', $token)
                || preg_match('/^\$\d+/', $token)
                || preg_match('/^\.\d{1,2}\$$/', $token)) {
                $consumed[] = $i;
                $priceIndex = $i;
                break;
            }
        }

        // KELP-style pattern: KELP $3.44 COOP ... — only if a price was actually
        // detected, treat the very next token as the consignor (or lister, if
        // configured). Without this guard the loose 2-5-letter heuristic
        // mis-tags trailing words like "MVP" in "HAWK SHELF B3 MVP".
        if ($result['consignor'] === null && $result['lister_initials'] === null && $priceIndex !== null) {
            $next = $priceIndex + 1;
            if (isset($tokens[$next]) && $this->isListerCode($tokens[$next])) {
                $result['lister_initials'] = strtoupper($tokens[$next]);
                $consumed[] = $next;
            } elseif (isset($tokens[$next]) && $this->isConsignorCode($tokens[$next])) {
                $result['consignor'] = strtoupper($tokens[$next]);
                $consumed[] = $next;

                if (isset($tokens[$next + 1]) && preg_match('/^\d{4,6}$/', $tokens[$next + 1])) {
                    $result['item_id'] = $tokens[$next + 1];
                    $consumed[] = $next + 1;
                }
            }
        }

        // Remove consumed tokens and extract location keywords from the rest
        $locationTokens = [];
        foreach ($tokens as $i => $token) {
            if (! in_array($i, $consumed)) {
                $locationTokens[] = $token;
            }
        }

        $locationString = implode(' ', $locationTokens);

        return $this->extractLocationKeywords($result, $locationString);
    }

    /**
     * Extract location keywords from a string using regex patterns.
     * Keywords can appear in any order.
     */
    private function extractLocationKeywords(array $result, string $text): array
    {
        if (trim($text) === '') {
            return $result;
        }

        // Normalise period-suffix item IDs glued to location tokens, e.g.
        // "Shelf B3.19586" -> "Shelf B3", "Tub.28215" -> "Tub". eBay sellers
        // sometimes append the item number with a period and no space, which
        // would otherwise stop the shelf/tub regexes from matching.
        $text = preg_replace('/\b([A-Za-z]\w*)\.\d{4,7}\b/', '$1', $text);

        // Room: RM/Rm/rm/ROOM followed by optional space and 2-4 digits
        if (preg_match('/\b(?:RM|Rm|rm|ROOM)\s*(\d{2,4})\b/i', $text, $m)) {
            $result['room'] = $m[1];
            $text = str_replace($m[0], ' ', $text);
        }

        // Shelf: SHELF/SHLF/SEHFL/Shelf followed by space and alphanumeric ID
        if (preg_match('/\b(?:SHELF|SHLF|SEHFL|Shlf)\s+([A-Za-z0-9][\w-]*)/i', $text, $m)) {
            $result['shelf'] = strtoupper($m[1]);
            $text = str_replace($m[0], ' ', $text);
        }

        // Tub: TUB followed by space and name
        if (preg_match('/\bTUB\s+([A-Za-z0-9$][\w$\'-]*)/i', $text, $m)) {
            $result['container_type'] = 'tub';
            $result['container_name'] = strtoupper($m[1]);
            $text = str_replace($m[0], ' ', $text);
        }

        // Box: BOX followed by space and name
        if ($result['container_type'] === null && preg_match('/\bBOX\s+([A-Za-z0-9][\w-]*)/i', $text, $m)) {
            $result['container_type'] = 'box';
            $result['container_name'] = strtoupper($m[1]);
            $text = str_replace($m[0], ' ', $text);
        }

        // Level: LVL/Lvl/lvl followed by optional space and digit
        if (preg_match('/\bLVL\s*(\d)\b/i', $text, $m)) {
            $result['level'] = $m[1];
            $text = str_replace($m[0], ' ', $text);
        }

        // Ground level
        if ($result['level'] === null && preg_match('/\bGROUND\b/i', $text, $m)) {
            $result['level'] = 'GROUND';
            $text = str_replace($m[0], ' ', $text);
        }

        // Special areas
        $textUpper = strtoupper($text);
        foreach (self::SPECIAL_AREAS as $area) {
            if (preg_match('/\b'.preg_quote($area, '/').'\b/i', $textUpper)) {
                $result['special_area'] = $area === 'MEZZANINE' ? 'MEZZ' : $area;
                break;
            }
        }

        return $result;
    }

    /**
     * Check if a token looks like a consignor code.
     *
     * Heuristic (no fixed whitelist): 2-5 uppercase letters, not a known
     * location keyword, not a digit-bearing token (which is more likely an
     * item id or product SKU fragment).
     */
    private function isConsignorCode(string $token): bool
    {
        $upper = strtoupper($token);

        if (! preg_match('/^[A-Z]{2,5}$/', $upper)) {
            return false;
        }

        if ($this->isLocationKeyword($upper)) {
            return false;
        }

        // Avoid self-tagging the ARC owner as a consignor of itself.
        if ($upper === 'ARC') {
            return false;
        }

        // Owners that recur in the org's data are never consignors. Without
        // this guard the loose 2-5-letter heuristic mis-tags owner codes
        // (HAWK/FERN/PINE/REEF etc.) as consignors when they appear as the
        // second token. The list is supplied by EbayStoreImportService from
        // org config.
        if (in_array($upper, $this->knownOwners, true)) {
            return false;
        }

        // Staff lister initials (ABC/DEF/GHI) are never consignors — they
        // route to `lister_initials` via isListerCode(). Without this guard
        // they would spawn spurious Supplier rows.
        if (in_array($upper, $this->knownListers, true)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a token is a configured staff/lister code. Listers never
     * become Suppliers; they only attach metadata to the product.
     */
    private function isListerCode(string $token): bool
    {
        if ($this->knownListers === []) {
            return false;
        }

        $upper = strtoupper($token);

        return in_array($upper, $this->knownListers, true);
    }

    /**
     * Check if a token is a location keyword (not a consignor or owner).
     */
    private function isLocationKeyword(string $token): bool
    {
        $upper = strtoupper($token);
        $keywords = ['SHELF', 'SHLF', 'SEHFL', 'TUB', 'BOX', 'RM', 'ROOM', 'LVL', 'LEVEL', 'GROUND',
            'FOH', 'CART', 'MEZZ', 'MEZZANINE', 'RACK', 'BIN', 'TOP', 'BOTTOM'];

        return in_array($upper, $keywords);
    }

    /**
     * Check if a token looks like an ARC product SKU (dash-delimited identifier).
     */
    private function isProductSku(string $token): bool
    {
        // Must contain at least one dash
        if (! str_contains($token, '-')) {
            return false;
        }

        // Must start with a letter or digit, contain alphanumeric + dashes
        // Examples: IND-18979-9, PO-1033-1022-312, CS-1043-1020-102, G73JH-X1, ELEC-71905-532
        if (! preg_match('/^[A-Z0-9][\w-]+$/i', $token)) {
            return false;
        }

        // Exclude shelf-like patterns (single letter/number + dash + single digit, like A-1, CM-2)
        // These are shelf sub-identifiers, not product SKUs
        if (preg_match('/^[A-Z]{1,3}-\d{1}$/i', $token)) {
            return false;
        }

        // Must have a segment with 3+ digits (product IDs have numeric sequences)
        if (preg_match('/\d{3,}/', $token)) {
            return true;
        }

        // Or be a recognized prefix format (2+ letter prefix, dash, then alphanumeric)
        if (preg_match('/^[A-Z]{2,}-[A-Z0-9]/i', $token)) {
            return true;
        }

        return false;
    }
}
