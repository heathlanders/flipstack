<?php

namespace App\Models\Analytics;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessHealthScore extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'score_date',
        'overall_score',
        'dimensions',
        'dimension_details',
        'alerts',
    ];

    protected $casts = [
        'score_date' => 'date',
        'overall_score' => 'decimal:2',
        'dimensions' => 'array',
        'dimension_details' => 'array',
        'alerts' => 'array',
    ];

    public function getHealthLevelAttribute(): string
    {
        $score = (float) $this->overall_score;

        if ($score >= 90) {
            return 'excellent';
        }

        if ($score >= 70) {
            return 'good';
        }

        if ($score >= 50) {
            return 'warning';
        }

        return 'critical';
    }
}
