<?php

namespace App\Services\Ebay;

use Generator;
use RuntimeException;

class EbayCsvParser
{
    public const HEADER_ITEM_NUMBER = 'Item number';

    public const HEADER_TITLE = 'Title';

    public const HEADER_VARIATION = 'Variation details';

    public const HEADER_SKU = 'Custom label (SKU)';

    public const REQUIRED_HEADERS = [
        self::HEADER_ITEM_NUMBER,
        self::HEADER_TITLE,
        self::HEADER_SKU,
    ];

    /**
     * Validate that a parsed CSV header row contains all required eBay columns.
     */
    public static function validateHeader(array $header): bool
    {
        $normalized = self::normalizeHeader($header);

        foreach (self::REQUIRED_HEADERS as $required) {
            if (! in_array($required, $normalized, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Read just the header row from a CSV file (BOM-stripped, trimmed).
     * Returns null if the file cannot be read.
     */
    public static function readHeader(string $absolutePath): ?array
    {
        $handle = @fopen($absolutePath, 'r');
        if ($handle === false) {
            return null;
        }
        $header = self::readCsvRow($handle);
        fclose($handle);

        if ($header === false) {
            return null;
        }

        return self::normalizeHeader($header);
    }

    /**
     * Strip a UTF-8 BOM from the first cell and trim each header.
     * eBay Seller Hub exports include a leading BOM that breaks key lookups.
     */
    private static function normalizeHeader(array $header): array
    {
        $bom = "\xEF\xBB\xBF";
        $normalized = [];
        foreach ($header as $idx => $col) {
            $col = (string) $col;
            if ($idx === 0 && str_starts_with($col, $bom)) {
                $col = substr($col, strlen($bom));
            }
            $normalized[] = trim($col);
        }

        return $normalized;
    }

    /**
     * Read one row from a CSV handle. Wrapper for forward-compat with PHP 8.4
     * fgetcsv signature change (explicit $escape).
     */
    private static function readCsvRow($handle): array|false
    {
        return fgetcsv($handle, 0, ',', '"', '');
    }

    /**
     * Count data rows (excluding header). Streams the file to handle 60k+ rows.
     */
    public static function countDataRows(string $absolutePath): int
    {
        $handle = @fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open CSV: {$absolutePath}");
        }

        $count = 0;
        // skip header
        if (self::readCsvRow($handle) === false) {
            fclose($handle);

            return 0;
        }

        while (($row = self::readCsvRow($handle)) !== false) {
            // Skip blank lines (single null cell from fgetcsv on empty line)
            if ($row === [null] || $row === [false]) {
                continue;
            }
            $count++;
        }

        fclose($handle);

        return $count;
    }

    /**
     * Stream rows as associative arrays keyed by header. Yields rows starting at
     * 0-based $offset (data rows, header excluded). Stops after $limit rows.
     */
    public static function streamRows(string $absolutePath, int $offset, int $limit): Generator
    {
        $handle = @fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open CSV: {$absolutePath}");
        }

        try {
            $header = self::readCsvRow($handle);
            if ($header === false) {
                return;
            }
            $header = self::normalizeHeader($header);

            $rowIndex = 0;
            $yielded = 0;

            while (($row = self::readCsvRow($handle)) !== false) {
                if ($row === [null] || $row === [false]) {
                    continue;
                }
                if ($rowIndex < $offset) {
                    $rowIndex++;

                    continue;
                }
                if ($yielded >= $limit) {
                    break;
                }

                $assoc = [];
                foreach ($header as $idx => $colName) {
                    $assoc[$colName] = $row[$idx] ?? null;
                }

                yield $rowIndex => $assoc;

                $rowIndex++;
                $yielded++;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Build the eBay item-array shape expected by EbayStoreImportService::createProductFromEbayItem().
     * CSV provides only item id, title, and custom SKU; everything else is filled with safe defaults
     * so downstream code can run unchanged.
     */
    public static function rowToEbayItem(array $row): array
    {
        $itemId = trim((string) ($row[self::HEADER_ITEM_NUMBER] ?? ''));
        $sku = trim((string) ($row[self::HEADER_SKU] ?? ''));
        $title = trim((string) ($row[self::HEADER_TITLE] ?? ''));

        return [
            'item_id' => $itemId,
            'sku' => $sku,
            'title' => $title !== '' ? $title : ('eBay item '.$itemId),
            'description' => '',
            'start_price' => 0,
            'currency' => 'USD',
            // The CSV is eBay's "active listings" report — every row is a live
            // listing with at least 1 unit available, so 1 is the right default.
            // Defaulting to 0 made imports look empty (InventoryLevel.quantity_on_hand=0
            // hides the row from location detail pages and "Total Products" counts).
            'available_quantity' => 1,
            'quantity' => 1,
            'quantity_sold' => 0,
            'hit_count' => 0,
            'watch_count' => 0,
            // CSV stubs have no real condition data. Default to "Used" so the
            // ebay_listings.condition NOT NULL constraint is satisfied; the
            // user can update individual products later when enriching.
            'condition_id' => '4000',
            'condition_display_name' => 'Used',
            'pictures' => [],
            'item_specifics' => [],
            'category_id' => null,
            'category_name' => null,
            'secondary_category_id' => null,
            'secondary_category_name' => null,
            'epid' => null,
            'store_category_id' => null,
            'store_category_name' => null,
            'listing_type' => 'FixedPriceItem',
            'listing_duration' => '',
            'view_item_url' => $itemId !== '' ? 'https://www.ebay.com/itm/'.$itemId : null,
            'start_time' => null,
            'end_time' => null,
            'shipping_details' => null,
            'return_policy' => null,
            'best_offer' => null,
            'seller_profiles' => null,
        ];
    }
}
