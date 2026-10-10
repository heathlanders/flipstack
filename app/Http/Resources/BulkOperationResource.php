<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BulkOperationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operation_type' => $this->operation_type,
            'operation_type_label' => $this->operation_type_label,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'total_items' => $this->total_items,
            'processed_items' => $this->processed_items,
            'failed_items' => $this->failed_items,
            'progress_percent' => $this->progress_percent,
            'error_count' => is_array($this->errors) ? count($this->errors) : 0,
            'has_download' => ! empty($this->result_file_path) && $this->status === 'completed',
            'is_cancellable' => $this->isCancellable(),
            'is_retryable' => $this->isRetryable(),
            'is_running' => $this->isRunning(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'duration' => $this->when($this->started_at && $this->completed_at,
                fn () => $this->started_at->diffForHumans($this->completed_at, true)),
        ];
    }
}
