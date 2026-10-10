<?php

namespace App\Services\Inventory;

use App\Models\Inventory\WarehouseBin;
use App\Models\Inventory\WarehouseZone;
use Illuminate\Support\Facades\Log;

class BinManagementService
{
    public function createZone(array $data, string $organizationId): WarehouseZone
    {
        return WarehouseZone::create([
            'organization_id' => $organizationId,
            'location_id' => $data['location_id'],
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'type' => $data['type'] ?? 'storage',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function createBin(array $data): WarehouseBin
    {
        $bin = WarehouseBin::create([
            'zone_id' => $data['zone_id'],
            'code' => $data['code'],
            'label' => $data['label'] ?? null,
            'aisle' => $data['aisle'] ?? null,
            'rack' => $data['rack'] ?? null,
            'shelf' => $data['shelf'] ?? null,
            'bin_position' => $data['bin_position'] ?? null,
            'max_capacity' => $data['max_capacity'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'barcode' => $data['barcode'] ?? $this->generateBarcode($data),
        ]);

        Log::info('Warehouse bin created', ['bin_id' => $bin->id, 'code' => $bin->code]);

        return $bin;
    }

    public function generateBarcode(array $data): string
    {
        $zone = WarehouseZone::find($data['zone_id']);

        return strtoupper(($zone?->code ?? 'X').'-'.$data['code']);
    }

    public function generateBinsForZone(WarehouseZone $zone, int $aisles, int $racks, int $shelves): int
    {
        $count = 0;
        for ($a = 1; $a <= $aisles; $a++) {
            for ($r = 1; $r <= $racks; $r++) {
                for ($s = 1; $s <= $shelves; $s++) {
                    $code = sprintf('%02d-%02d-%02d', $a, $r, $s);
                    $this->createBin([
                        'zone_id' => $zone->id,
                        'code' => $code,
                        'aisle' => (string) $a,
                        'rack' => (string) $r,
                        'shelf' => (string) $s,
                    ]);
                    $count++;
                }
            }
        }

        return $count;
    }

    public function findByBarcode(string $barcode): ?WarehouseBin
    {
        return WarehouseBin::where('barcode', $barcode)->first();
    }

    public function getZonesWithBins(string $locationId): array
    {
        return WarehouseZone::where('location_id', $locationId)
            ->with(['bins' => fn ($q) => $q->orderBy('code')])
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }
}
