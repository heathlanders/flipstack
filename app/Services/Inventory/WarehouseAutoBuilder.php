<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\WarehouseBin;
use App\Models\Inventory\WarehouseZone;
use Illuminate\Support\Facades\Log;

class WarehouseAutoBuilder
{
    /**
     * In-memory cache to avoid repeated DB lookups during batch imports.
     *
     * @var array<string, WarehouseZone>
     */
    private array $zoneCache = [];

    /**
     * @var array<string, WarehouseBin>
     */
    private array $binCache = [];

    /**
     * Build warehouse hierarchy from parsed location data.
     * Returns the WarehouseBin to link to InventoryLevel, or null if insufficient data.
     *
     * When room/special_area are absent, items are placed under a default
     * "Main Floor" zone so positional data (shelf/container/level) still maps
     * to a real bin.
     */
    public function buildFromParsedLocation(
        array $parsedLocation,
        string $organizationId,
        string $locationId
    ): ?WarehouseBin {
        $room = $parsedLocation['room'] ?? null;
        $specialArea = $parsedLocation['special_area'] ?? null;
        $shelf = $parsedLocation['shelf'] ?? null;
        $containerType = $parsedLocation['container_type'] ?? null;
        $containerName = $parsedLocation['container_name'] ?? null;
        $level = $parsedLocation['level'] ?? null;

        try {
            $zone = $this->resolveZone($organizationId, $locationId, $room, $specialArea);
            $bin = $this->resolveBin($zone, $shelf, $containerType, $containerName, $level);

            return $bin;
        } catch (\Exception $e) {
            Log::warning('WarehouseAutoBuilder: failed to build hierarchy', [
                'room' => $room,
                'shelf' => $shelf,
                'container' => $containerName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve or create the zone for a room or special area.
     */
    private function resolveZone(
        string $organizationId,
        string $locationId,
        ?string $room,
        ?string $specialArea
    ): WarehouseZone {
        if ($room !== null) {
            $code = 'RM-'.$room;
            $name = 'Room '.$room;
        } elseif ($specialArea !== null) {
            $code = strtoupper($specialArea);
            $name = match ($code) {
                'FOH' => 'Front of House',
                'MEZZ' => 'Mezzanine',
                'CART' => 'Cart Area',
                'RACK' => 'Rack Storage',
                default => ucfirst(strtolower($code)),
            };
        } else {
            $code = 'MAIN-FLOOR';
            $name = 'Main Floor';
        }

        $cacheKey = $locationId.':'.$code;

        if (isset($this->zoneCache[$cacheKey])) {
            return $this->zoneCache[$cacheKey];
        }

        $zone = WarehouseZone::firstOrCreate(
            ['location_id' => $locationId, 'code' => $code],
            [
                'organization_id' => $organizationId,
                'name' => $name,
                'type' => WarehouseZone::TYPE_STORAGE,
                'is_active' => true,
            ]
        );

        $this->zoneCache[$cacheKey] = $zone;

        return $zone;
    }

    /**
     * Resolve or create the bin for a shelf + container + level combination.
     */
    private function resolveBin(
        WarehouseZone $zone,
        ?string $shelf,
        ?string $containerType,
        ?string $containerName,
        ?string $level
    ): WarehouseBin {
        $code = $this->generateBinCode($shelf, $containerType, $containerName, $level);
        $label = $this->generateBinLabel($shelf, $containerType, $containerName, $level);

        $cacheKey = $zone->id.':'.$code;

        if (isset($this->binCache[$cacheKey])) {
            return $this->binCache[$cacheKey];
        }

        $bin = WarehouseBin::firstOrCreate(
            ['zone_id' => $zone->id, 'code' => $code],
            [
                'label' => $label,
                'rack' => $shelf,
                'shelf' => $level,
                'bin_position' => $containerName,
                'is_active' => true,
                'barcode' => strtoupper($zone->code.'-'.$code),
            ]
        );

        $this->binCache[$cacheKey] = $bin;

        return $bin;
    }

    /**
     * Generate a deterministic bin code from location components.
     */
    public function generateBinCode(
        ?string $shelf,
        ?string $containerType,
        ?string $containerName,
        ?string $level
    ): string {
        $parts = [];

        if ($shelf !== null) {
            $parts[] = 'SHLF-'.strtoupper($shelf);
        }

        if ($level !== null) {
            $levelCode = strtoupper($level) === 'GROUND' ? 'GND' : $level;
            $parts[] = 'LVL-'.$levelCode;
        }

        if ($containerType !== null && $containerName !== null) {
            $prefix = strtoupper($containerType) === 'BOX' ? 'BOX' : 'TUB';
            $parts[] = $prefix.'-'.strtoupper($containerName);
        }

        if (empty($parts)) {
            return 'DEFAULT';
        }

        return implode('-', $parts);
    }

    /**
     * Generate a human-readable label for the bin.
     */
    private function generateBinLabel(
        ?string $shelf,
        ?string $containerType,
        ?string $containerName,
        ?string $level
    ): string {
        $parts = [];

        if ($shelf !== null) {
            $parts[] = 'Shelf '.$shelf;
        }

        if ($level !== null) {
            $parts[] = strtoupper($level) === 'GROUND' ? 'Ground Level' : 'Level '.$level;
        }

        if ($containerType !== null && $containerName !== null) {
            $parts[] = ucfirst($containerType).' '.ucfirst(strtolower($containerName));
        }

        if (empty($parts)) {
            return 'Default';
        }

        return implode(', ', $parts);
    }

    /**
     * Clear the in-memory cache (useful between import batches or in tests).
     */
    public function clearCache(): void
    {
        $this->zoneCache = [];
        $this->binCache = [];
    }

    /**
     * Get or create the main warehouse InventoryLocation for an organization.
     */
    public function resolveMainWarehouse(string $organizationId): InventoryLocation
    {
        return InventoryLocation::firstOrCreate(
            ['organization_id' => $organizationId, 'code' => 'WAREHOUSE'],
            ['name' => 'Main Warehouse', 'is_active' => true, 'is_default' => true]
        );
    }
}
