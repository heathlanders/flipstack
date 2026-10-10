<?php

namespace App\Services\Ebay;

use App\Models\Ebay\EbayListing;
use App\Models\Ebay\EbayPromotedCampaign;
use App\Models\Ebay\EbayPromotedListing;
use Illuminate\Support\Facades\Log;

class EbayPromotedListingsService
{
    public function createCampaign(array $data, string $orgId): EbayPromotedCampaign
    {
        $data['organization_id'] = $orgId;

        return EbayPromotedCampaign::create($data);
    }

    public function updateCampaign(EbayPromotedCampaign $campaign, array $data): EbayPromotedCampaign
    {
        $campaign->update($data);

        return $campaign->fresh();
    }

    public function pauseCampaign(EbayPromotedCampaign $campaign): void
    {
        $campaign->update(['status' => EbayPromotedCampaign::STATUS_PAUSED]);
    }

    public function resumeCampaign(EbayPromotedCampaign $campaign): void
    {
        $campaign->update(['status' => EbayPromotedCampaign::STATUS_ACTIVE]);
    }

    public function endCampaign(EbayPromotedCampaign $campaign): void
    {
        $campaign->update(['status' => EbayPromotedCampaign::STATUS_ENDED]);
    }

    public function addListingToCampaign(EbayPromotedCampaign $campaign, int $ebayListingId, float $adRate): EbayPromotedListing
    {
        $ebayListing = EbayListing::findOrFail($ebayListingId);

        return EbayPromotedListing::create([
            'campaign_id' => $campaign->id,
            'ebay_listing_id' => $ebayListingId,
            'product_id' => $ebayListing->product_id,
            'ad_rate_percentage' => $adRate,
            'status' => 'active',
        ]);
    }

    public function removeListingFromCampaign(EbayPromotedListing $listing): void
    {
        $listing->update(['status' => 'removed']);
    }

    public function getDashboardStats(string $orgId): array
    {
        $campaigns = EbayPromotedCampaign::where('organization_id', $orgId)->get();
        $campaignIds = $campaigns->pluck('id');

        $listings = EbayPromotedListing::whereIn('campaign_id', $campaignIds)->get();

        return [
            'total_spend' => (float) $listings->sum('ad_fees'),
            'total_revenue' => (float) $listings->sum('sales_amount'),
            'overall_roas' => $listings->sum('ad_fees') > 0
                ? round($listings->sum('sales_amount') / $listings->sum('ad_fees'), 2)
                : 0,
            'active_campaigns' => $campaigns->where('status', EbayPromotedCampaign::STATUS_ACTIVE)->count(),
        ];
    }

    public function syncStats(EbayPromotedCampaign $campaign): void
    {
        Log::info('EbayPromotedListingsService: syncStats would sync stats from eBay API', [
            'campaign_id' => $campaign->id,
            'ebay_campaign_id' => $campaign->ebay_campaign_id,
            'organization_id' => $campaign->organization_id,
        ]);
    }
}
