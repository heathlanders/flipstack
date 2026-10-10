<?php

namespace App\Http\Resources\Supplier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceMetricsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,

            // Period
            'period' => [
                'start' => $this->evaluation_period_start?->format('Y-m-d'),
                'end' => $this->evaluation_period_end?->format('Y-m-d'),
                'label' => $this->evaluation_period_start?->format('M d').' - '.$this->evaluation_period_end?->format('M d, Y'),
            ],

            // Submission Metrics
            'submissions' => [
                'total' => $this->total_submissions,
                'approved' => $this->approved_submissions,
                'rejected' => $this->rejected_submissions,
                'pending' => $this->pending_submissions,
                'approval_rate' => round($this->approval_rate ?? 0, 1),
            ],

            // Sales Metrics
            'sales' => [
                'items_listed' => $this->items_listed,
                'items_sold' => $this->items_sold,
                'sell_through_rate' => round($this->sell_through_rate ?? 0, 1),
                'total_revenue' => round($this->total_revenue ?? 0, 2),
                'average_sale_price' => round($this->average_sale_price ?? 0, 2),
                'average_days_to_sell' => round($this->average_days_to_sell ?? 0, 1),
            ],

            // Return Metrics
            'returns' => [
                'total' => $this->total_returns,
                'rate' => round($this->return_rate ?? 0, 1),
                'value' => round($this->return_value ?? 0, 2),
            ],

            // Quality Scores
            'quality' => [
                'overall_score' => round($this->quality_score ?? 0, 1),
                'accuracy_score' => round($this->accuracy_score ?? 0, 1),
                'condition_accuracy' => round($this->condition_accuracy ?? 0, 1),
                'packaging_score' => round($this->packaging_score ?? 0, 1),
            ],

            // Compliance
            'compliance' => [
                'score' => round($this->compliance_score ?? 0, 1),
                'policy_violations' => $this->policy_violations,
                'late_submissions' => $this->late_submissions,
                'documentation_issues' => $this->documentation_issues,
            ],

            // Customer Satisfaction
            'customer' => [
                'rating' => round($this->customer_rating ?? 0, 1),
                'total_complaints' => $this->customer_complaints,
                'positive_feedback' => $this->positive_feedback_count,
                'negative_feedback' => $this->negative_feedback_count,
            ],

            // Overall Performance
            'overall_score' => round($this->overall_performance_score ?? 0, 1),
            'score_breakdown' => $this->score_breakdown,
            'score_weights' => $this->score_weights,

            // Tier Information
            'tier' => [
                'current' => $this->when($this->relationLoaded('currentTier'), [
                    'id' => $this->currentTier?->id,
                    'name' => $this->currentTier?->tier_name,
                    'code' => $this->currentTier?->tier_code,
                ]),
                'previous' => $this->when($this->relationLoaded('previousTier'), [
                    'id' => $this->previousTier?->id,
                    'name' => $this->previousTier?->tier_name,
                    'code' => $this->previousTier?->tier_code,
                ]),
                'change_date' => $this->tier_change_date?->format('Y-m-d'),
                'change_pending' => $this->tier_change_pending,
            ],

            // Insights
            'insights' => [
                'strengths' => $this->strengths ?? [],
                'weaknesses' => $this->weaknesses ?? [],
                'recommendations' => $this->improvement_recommendations ?? [],
            ],

            // Status
            'status' => $this->status,
            'is_finalized' => $this->status === 'finalized',

            // Review info
            'review' => $this->when($this->reviewed_by || $this->reviewed_at, [
                'reviewed_by' => $this->reviewedBy?->name,
                'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),
                'notes' => $this->review_notes,
            ]),

            // Calculation info
            'calculated_by' => $this->calculatedBy?->name,
            'calculated_at' => $this->calculated_at?->format('Y-m-d H:i:s'),

            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
