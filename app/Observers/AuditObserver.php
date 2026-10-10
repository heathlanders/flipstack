<?php

namespace App\Observers;

use App\Support\AuditHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->safely($model, 'created', function () use ($model) {
            $cfg = $this->config($model);
            AuditHelper::event(
                action: $this->actionFor($model, 'created', $cfg),
                description: $this->describe($model, 'created'),
                subject: $model,
                metadata: array_merge(
                    $cfg['metadata'],
                    ['attributes' => $this->filtered($model->getAttributes(), $cfg['exclude'])],
                ),
                severity: $this->severityFor('created', $cfg),
                category: $cfg['category'],
            );
        });
    }

    public function updated(Model $model): void
    {
        $this->safely($model, 'updated', function () use ($model) {
            $cfg = $this->config($model);
            $changes = $this->diffChanges($model, $cfg['exclude']);
            if (empty($changes)) {
                return;
            }
            AuditHelper::event(
                action: $this->actionFor($model, 'updated', $cfg),
                description: $this->describe($model, 'updated', $changes),
                subject: $model,
                metadata: array_merge($cfg['metadata'], ['changes' => $changes]),
                severity: $this->severityFor('updated', $cfg),
                category: $cfg['category'],
            );
        });
    }

    public function deleted(Model $model): void
    {
        $this->safely($model, 'deleted', function () use ($model) {
            $cfg = $this->config($model);
            AuditHelper::event(
                action: $this->actionFor($model, 'deleted', $cfg),
                description: $this->describe($model, 'deleted'),
                subject: $model,
                metadata: $cfg['metadata'],
                severity: $this->severityFor('deleted', $cfg),
                category: $cfg['category'],
            );
        });
    }

    public function restored(Model $model): void
    {
        $this->safely($model, 'restored', function () use ($model) {
            $cfg = $this->config($model);
            AuditHelper::event(
                action: $this->actionFor($model, 'restored', $cfg),
                description: $this->describe($model, 'restored'),
                subject: $model,
                metadata: $cfg['metadata'],
                severity: $this->severityFor('restored', $cfg),
                category: $cfg['category'],
            );
        });
    }

    /**
     * Skip-check + try/catch wrapper. Observers must NEVER throw from a domain operation —
     * a broken audit row cannot fail the host save/delete.
     */
    protected function safely(Model $model, string $event, callable $fn): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::error('AuditObserver failed', [
                'model' => get_class($model),
                'id' => $model->getKey(),
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function shouldSkip(Model $model): bool
    {
        return (bool) ($model->withoutAudit ?? false);
    }

    protected function config(Model $model): array
    {
        if (method_exists($model, 'auditConfig')) {
            return $model->auditConfig() + [
                'category' => 'general',
                'severity' => null,
                'action_prefix' => null,
                'exclude' => [],
                'metadata' => [],
            ];
        }

        return [
            'category' => 'general',
            'severity' => null,
            'action_prefix' => null,
            'exclude' => [],
            'metadata' => [],
        ];
    }

    protected function actionFor(Model $model, string $event, array $cfg): string
    {
        $base = $cfg['action_prefix'] ?? Str::snake(class_basename($model));

        return "{$base}.{$event}";
    }

    protected function severityFor(string $event, array $cfg): string
    {
        if (! empty($cfg['severity'])) {
            return $cfg['severity'];
        }

        return $event === 'deleted' ? 'warning' : 'info';
    }

    protected function describe(Model $model, string $event, array $changes = []): string
    {
        $label = $this->entityLabel($model);
        if ($event === 'updated' && ! empty($changes)) {
            return "{$label} updated (".implode(', ', array_keys($changes)).')';
        }

        return "{$label} {$event}";
    }

    protected function entityLabel(Model $model): string
    {
        foreach (['name', 'title', 'sku', 'po_number', 'order_number', 'email'] as $attr) {
            if (! empty($model->{$attr})) {
                return class_basename($model).': '.$model->{$attr};
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    protected function diffChanges(Model $model, array $modelExclude): array
    {
        $exclude = $this->excludedFields($modelExclude);
        $diff = [];

        foreach ($model->getDirty() as $field => $newValue) {
            if (in_array($field, $exclude, true)) {
                continue;
            }
            $diff[$field] = [
                'old' => $model->getOriginal($field),
                'new' => $newValue,
            ];
        }

        return $diff;
    }

    protected function filtered(array $attributes, array $modelExclude): array
    {
        $exclude = $this->excludedFields($modelExclude);

        return collect($attributes)->except($exclude)->all();
    }

    protected function excludedFields(array $modelExclude): array
    {
        return array_merge(
            ['updated_at', 'created_at', 'deleted_at', 'remember_token', 'password'],
            $modelExclude,
        );
    }
}
