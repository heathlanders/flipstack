<?php

namespace App\Services;

use App\Models\Catalog\Product;
use App\Models\Organization\Organization;
use Exception;
use Illuminate\Support\Facades\DB;

class OrganizationSkuService
{
    /**
     * Generate a suggested SKU prefix from organization name
     */
    public function suggestPrefix(string $organizationName): string
    {
        // Extract letters and numbers, convert to uppercase
        $cleaned = preg_replace('/[^A-Za-z0-9]/', '', $organizationName);
        $cleaned = strtoupper($cleaned);

        // Take first 3-6 characters depending on length
        if (strlen($cleaned) <= 3) {
            $prefix = $cleaned;
        } elseif (strlen($cleaned) <= 6) {
            $prefix = substr($cleaned, 0, 4);
        } else {
            $prefix = substr($cleaned, 0, 5);
        }

        // Ensure minimum 3 characters
        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, '0');
        }

        return $prefix;
    }

    /**
     * Check if a SKU prefix is available
     */
    public function isPrefixAvailable(string $prefix): bool
    {
        return ! Organization::where('sku_prefix', $prefix)->exists();
    }

    /**
     * Set the SKU prefix for an organization
     */
    public function setPrefix(Organization $organization, string $prefix, int $userId): bool
    {
        // Validate prefix format
        if (! preg_match('/^[A-Z0-9]{3,10}$/', $prefix)) {
            throw new Exception('SKU prefix must be 3-10 characters, alphanumeric only (A-Z, 0-9)');
        }

        // Check if organization already has a locked prefix
        if ($organization->sku_prefix_locked) {
            throw new Exception('SKU prefix is already locked and cannot be changed');
        }

        // Check availability
        if (! $this->isPrefixAvailable($prefix)) {
            throw new Exception('SKU prefix already in use by another organization');
        }

        try {
            DB::transaction(function () use ($organization, $prefix, $userId) {
                $organization->update([
                    'sku_prefix' => $prefix,
                    'sku_prefix_locked' => true,
                    'sku_prefix_set_at' => now(),
                    'sku_prefix_set_by' => $userId,
                ]);
            });

            return true;
        } catch (Exception $e) {
            throw new Exception('Failed to set SKU prefix: '.$e->getMessage());
        }
    }

    /**
     * Generate the next internal SKU for an organization
     */
    public function generateInternalSku(Organization $organization): string
    {
        if (! $organization->sku_prefix) {
            throw new Exception('Organization does not have a SKU prefix set');
        }

        try {
            return DB::transaction(function () use ($organization) {
                // Increment counter atomically
                $organization->increment('sku_counter');
                $organization->refresh();

                // Generate SKU: PREFIX-000001
                $counter = str_pad($organization->sku_counter, 6, '0', STR_PAD_LEFT);
                $sku = $organization->sku_prefix.'-'.$counter;

                // Ensure uniqueness (very unlikely collision but safety first)
                $attempts = 0;
                while (Product::where('internal_sku', $sku)->exists() && $attempts < 10) {
                    $organization->increment('sku_counter');
                    $organization->refresh();
                    $counter = str_pad($organization->sku_counter, 6, '0', STR_PAD_LEFT);
                    $sku = $organization->sku_prefix.'-'.$counter;
                    $attempts++;
                }

                if ($attempts >= 10) {
                    throw new Exception('Unable to generate unique internal SKU after multiple attempts');
                }

                return $sku;
            });
        } catch (Exception $e) {
            throw new Exception('Failed to generate internal SKU: '.$e->getMessage());
        }
    }

    /**
     * Get available prefix suggestions based on organization name
     */
    public function getAvailableSuggestions(string $organizationName, int $limit = 5): array
    {
        $baseSuggestion = $this->suggestPrefix($organizationName);
        $suggestions = [];

        // Try the base suggestion first
        if ($this->isPrefixAvailable($baseSuggestion)) {
            $suggestions[] = $baseSuggestion;
        }

        // Generate variations
        $counter = 1;
        while (count($suggestions) < $limit && $counter <= 10) {
            $variation = $baseSuggestion.$counter;

            if (strlen($variation) <= 10 && $this->isPrefixAvailable($variation)) {
                $suggestions[] = $variation;
            }

            $counter++;
        }

        return $suggestions;
    }

    /**
     * Validate that an organization can create products
     */
    public function canCreateProducts(Organization $organization): bool
    {
        return ! empty($organization->sku_prefix) && $organization->sku_prefix_locked;
    }

    /**
     * Get SKU prefix status for an organization
     */
    public function getPrefixStatus(Organization $organization): array
    {
        return [
            'has_prefix' => ! empty($organization->sku_prefix),
            'is_locked' => $organization->sku_prefix_locked,
            'prefix' => $organization->sku_prefix,
            'counter' => $organization->sku_counter,
            'next_sku' => $this->canCreateProducts($organization)
                ? $organization->sku_prefix.'-'.str_pad($organization->sku_counter + 1, 6, '0', STR_PAD_LEFT)
                : null,
            'can_create_products' => $this->canCreateProducts($organization),
            'set_at' => $organization->sku_prefix_set_at,
            'set_by' => $organization->sku_prefix_set_by,
        ];
    }
}
