<?php

namespace App\Traits;

use App\Observers\AuditObserver;

/**
 * Auto-emits audit rows to user_activity_logs on create/update/delete/restore.
 *
 * Models opt in via `use LogsAudit;`. Configure via these (protected) properties:
 *   protected ?string $auditCategory = 'product';       // category column (default: general)
 *   protected ?string $auditSeverity = null;            // override default 'info' (delete -> warning)
 *   protected ?string $auditActionPrefix = 'product';   // override snake_case class basename
 *   protected array $auditExclude = ['sync_token'];     // fields ignored in diff/snapshot
 *   protected array $auditMetadata = ['source' => 'web']; // metadata merged into every emission
 *
 * Bulk operations bypass per-row audit:
 *   $model->withoutAudit = true; $model->save();
 *
 * Services should still emit explicit domain lifecycle events
 * (e.g. UserActivityLog::log('order.shipped', ...) or audit_event(...)).
 */
trait LogsAudit
{
    /**
     * Transient flag — set true to skip audit for the next save/delete on this instance.
     * Declared as a real property so Eloquent doesn't treat it as a model attribute.
     */
    public bool $withoutAudit = false;

    public static function bootLogsAudit(): void
    {
        static::observe(AuditObserver::class);
    }

    /**
     * Expose audit configuration to the observer. Override in your model if you need
     * dynamic resolution; default reads the protected $audit* properties.
     */
    public function auditConfig(): array
    {
        return [
            'category' => $this->auditCategory ?? 'general',
            'severity' => $this->auditSeverity ?? null,
            'action_prefix' => $this->auditActionPrefix ?? null,
            'exclude' => $this->auditExclude ?? [],
            'metadata' => $this->auditMetadata ?? [],
        ];
    }
}
