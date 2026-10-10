<?php

namespace App\Services\Pricing;

use App\Models\Amazon\AmazonListing;
use App\Models\Catalog\Product;
use App\Models\Pricing\RepricingLog;
use App\Models\Pricing\RepricingRule;
use Illuminate\Support\Facades\Log;

class RepricingEngine
{
    public function runForOrganization(string $organizationId): array
    {
        $rules = RepricingRule::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->active()
            ->get();

        $results = ['processed' => 0, 'changed' => 0, 'errors' => 0];

        foreach ($rules as $rule) {
            try {
                $ruleResult = $this->applyRule($rule, $organizationId);
                $results['processed'] += $ruleResult['processed'];
                $results['changed'] += $ruleResult['changed'];
                $rule->update(['last_run_at' => now()]);
            } catch (\Throwable $e) {
                $results['errors']++;
                Log::error('Repricing rule failed', ['rule_id' => $rule->id, 'error' => $e->getMessage()]);

                app(\App\Services\NotificationService::class)->dispatchEvent(
                    eventKey: 'pricing.rule.error',
                    organizationId: (int) $organizationId,
                    args: [
                        (string) $rule->id,
                        $rule->name ?? null,
                        $e->getMessage(),
                    ],
                    relatedId: (string) $rule->id,
                );
            }
        }

        return $results;
    }

    protected function applyRule(RepricingRule $rule, string $organizationId): array
    {
        $products = $this->getProductsForRule($rule, $organizationId);
        $processed = 0;
        $changed = 0;

        foreach ($products as $product) {
            $processed++;
            $newPrice = $this->calculateNewPrice($rule, $product);

            if ($newPrice === null || abs($newPrice - $product->selling_price) < 0.01) {
                continue;
            }

            // Apply price guards
            $newPrice = $this->applyGuards($rule, $product, $newPrice);
            if ($newPrice === null || abs($newPrice - $product->selling_price) < 0.01) {
                continue;
            }

            $oldPrice = $product->selling_price;
            $product->update(['selling_price' => $newPrice]);

            RepricingLog::create([
                'repricing_rule_id' => $rule->id,
                'product_id' => $product->id,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'reason' => "Strategy: {$rule->strategy}",
                'applied_at' => now(),
            ]);

            audit_event(
                action: 'pricing.repriced',
                description: "Repriced {$product->sku} from \${$oldPrice} to \${$newPrice} via rule '{$rule->name}'",
                subject: $product,
                metadata: [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'old_price' => (float) $oldPrice,
                    'new_price' => (float) $newPrice,
                    'strategy' => $rule->strategy,
                    'source' => 'cron',
                ],
                severity: 'info',
                category: 'pricing',
                organization: $rule->organization,
            );

            $changed++;
        }

        return ['processed' => $processed, 'changed' => $changed];
    }

    protected function getProductsForRule(RepricingRule $rule, string $organizationId)
    {
        $query = Product::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('status', 'active');

        if ($rule->scope_type === 'category' && ! empty($rule->scope_ids)) {
            $query->whereIn('category_id', $rule->scope_ids);
        } elseif ($rule->scope_type === 'product' && ! empty($rule->scope_ids)) {
            $query->whereIn('id', $rule->scope_ids);
        }

        return $query->get();
    }

    protected function calculateNewPrice(RepricingRule $rule, Product $product): ?float
    {
        $params = $rule->parameters ?? [];

        return match ($rule->strategy) {
            RepricingRule::STRATEGY_COST_PLUS => $this->costPlusStrategy($product, $params),
            RepricingRule::STRATEGY_BEAT_BY_AMOUNT => $this->beatByAmountStrategy($product, $params),
            RepricingRule::STRATEGY_BEAT_BY_PERCENT => $this->beatByPercentStrategy($product, $params),
            RepricingRule::STRATEGY_MAP_FLOOR => $this->mapFloorStrategy($product, $params),
            RepricingRule::STRATEGY_MATCH_BUY_BOX => $this->matchBuyBoxStrategy($rule, $product),
            RepricingRule::STRATEGY_BEAT_BUY_BOX => $this->beatBuyBoxStrategy($rule, $product, $params),
            default => null,
        };
    }

    /**
     * Match the Amazon Buy Box price exactly. Requires an active AmazonListing
     * for the product on a credential matching the rule's marketplace scope,
     * with a non-null buy_box_price (populated by UpdateAmazonBuyBoxPrices).
     */
    protected function matchBuyBoxStrategy(RepricingRule $rule, Product $product): ?float
    {
        $buyBox = $this->resolveBuyBoxPrice($rule, $product);

        return $buyBox !== null ? round($buyBox, 2) : null;
    }

    /**
     * Beat the Buy Box. Either beat_amount (dollars off) or beat_percent (% off)
     * via parameters; defaults to $0.01 below if neither is provided.
     */
    protected function beatBuyBoxStrategy(RepricingRule $rule, Product $product, array $params): ?float
    {
        $buyBox = $this->resolveBuyBoxPrice($rule, $product);

        if ($buyBox === null) {
            return null;
        }

        if (isset($params['beat_amount'])) {
            return round(max(0.01, $buyBox - (float) $params['beat_amount']), 2);
        }

        if (isset($params['beat_percent'])) {
            return round(max(0.01, $buyBox * (1 - ((float) $params['beat_percent']) / 100)), 2);
        }

        return round(max(0.01, $buyBox - 0.01), 2);
    }

    protected function resolveBuyBoxPrice(RepricingRule $rule, Product $product): ?float
    {
        if ($rule->marketplace !== 'amazon' && $rule->marketplace !== '*') {
            return null;
        }

        $listing = AmazonListing::where('product_id', $product->id)
            ->whereIn('status', [AmazonListing::STATUS_ACTIVE, AmazonListing::STATUS_CREATED])
            ->whereNotNull('buy_box_price')
            ->orderByDesc('last_synced_at')
            ->first();

        return $listing?->buy_box_price !== null ? (float) $listing->buy_box_price : null;
    }

    protected function costPlusStrategy(Product $product, array $params): ?float
    {
        $margin = $params['cost_plus_margin'] ?? 30;
        $cost = (float) $product->cost_price;

        return $cost > 0 ? round($cost * (1 + $margin / 100), 2) : null;
    }

    protected function beatByAmountStrategy(Product $product, array $params): ?float
    {
        $amount = $params['beat_amount'] ?? 1;
        $currentPrice = (float) $product->selling_price;

        return $currentPrice > $amount ? round($currentPrice - $amount, 2) : null;
    }

    protected function beatByPercentStrategy(Product $product, array $params): ?float
    {
        $percent = $params['beat_percent'] ?? 5;
        $currentPrice = (float) $product->selling_price;

        return round($currentPrice * (1 - $percent / 100), 2);
    }

    protected function mapFloorStrategy(Product $product, array $params): ?float
    {
        $msrp = (float) $product->msrp;

        return $msrp > 0 ? $msrp : null;
    }

    protected function applyGuards(RepricingRule $rule, Product $product, float $newPrice): ?float
    {
        $minPrice = $rule->getMinPrice();
        $maxPrice = $rule->getMaxPrice();
        $proposed = $newPrice;
        $guardHit = null;

        // Never go below cost
        $cost = (float) $product->cost_price;
        if ($cost > 0 && $newPrice < $cost) {
            $this->dispatchGuardHit($rule, $product, 'cost', $proposed, $cost);

            return null;
        }

        if ($minPrice !== null && $newPrice < $minPrice) {
            $newPrice = $minPrice;
            $guardHit = 'floor';
        }

        if ($maxPrice !== null && $newPrice > $maxPrice) {
            $newPrice = $maxPrice;
            $guardHit = 'ceiling';
        }

        $final = round($newPrice, 2);

        if ($guardHit !== null) {
            $this->dispatchGuardHit($rule, $product, $guardHit, $proposed, $final);
        }

        return $final;
    }

    protected function dispatchGuardHit(RepricingRule $rule, Product $product, string $guardType, float $proposed, float $final): void
    {
        if (! $product->organization_id) {
            return;
        }

        app(\App\Services\NotificationService::class)->dispatchEvent(
            eventKey: 'pricing.guard.hit',
            organizationId: (int) $product->organization_id,
            args: [
                (string) $rule->id,
                $rule->name ?? null,
                (string) $product->id,
                (string) ($product->name ?? 'Unknown product'),
                $guardType,
                round($proposed, 2),
                round($final, 2),
            ],
            relatedId: $rule->id.':'.$product->id.':'.$guardType,
        );
    }
}
