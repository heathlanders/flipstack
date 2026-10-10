<?php

namespace App\Models\Notification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'type',
        'channel',
        'subject',
        'body',
        'variables',
        'active',
    ];

    protected $casts = [
        'variables' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Get the tenant that owns the template.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Render the template with given data.
     */
    public function render(array $data): string
    {
        $body = $this->body;

        foreach ($data as $key => $value) {
            $body = str_replace('{'.$key.'}', $value, $body);
        }

        return $body;
    }

    /**
     * Render the subject with given data.
     */
    public function renderSubject(array $data): ?string
    {
        if (! $this->subject) {
            return null;
        }

        $subject = $this->subject;

        foreach ($data as $key => $value) {
            $subject = str_replace('{'.$key.'}', $value, $subject);
        }

        return $subject;
    }
}
