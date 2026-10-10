<?php

namespace App\Models\Supplier;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOnboarding extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'supplier_onboarding';

    protected $fillable = [
        'supplier_id',
        'organization_id',
        'status',
        'step1_application_complete',
        'step1_completed_at',
        'step1_data',
        'step2_documentation_complete',
        'step2_completed_at',
        'step2_data',
        'business_license_verified',
        'tax_documents_verified',
        'insurance_verified',
        'identity_verified',
        'step3_commission_complete',
        'step3_completed_at',
        'step3_data',
        'assigned_commission_structure_id',
        'settlement_preference',
        'step4_portal_complete',
        'step4_completed_at',
        'step4_data',
        'portal_credentials_sent',
        'portal_first_login',
        'tutorial_completed',
        'step5_submission_complete',
        'step5_completed_at',
        'step5_data',
        'first_submission_id',
        'submission_approved',
        'current_step',
        'completion_percentage',
        'started_at',
        'completed_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'rejection_reason',
        'assigned_to',
        'assigned_at',
        'reminder_count',
        'last_reminder_sent_at',
        'next_reminder_at',
        'internal_notes',
        'checklist_items',
    ];

    protected $casts = [
        'step1_application_complete' => 'boolean',
        'step1_completed_at' => 'datetime',
        'step1_data' => 'array',
        'step2_documentation_complete' => 'boolean',
        'step2_completed_at' => 'datetime',
        'step2_data' => 'array',
        'business_license_verified' => 'boolean',
        'tax_documents_verified' => 'boolean',
        'insurance_verified' => 'boolean',
        'identity_verified' => 'boolean',
        'step3_commission_complete' => 'boolean',
        'step3_completed_at' => 'datetime',
        'step3_data' => 'array',
        'step4_portal_complete' => 'boolean',
        'step4_completed_at' => 'datetime',
        'step4_data' => 'array',
        'portal_credentials_sent' => 'boolean',
        'portal_first_login' => 'datetime',
        'tutorial_completed' => 'boolean',
        'step5_submission_complete' => 'boolean',
        'step5_completed_at' => 'datetime',
        'step5_data' => 'array',
        'submission_approved' => 'boolean',
        'current_step' => 'integer',
        'completion_percentage' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'reminder_count' => 'integer',
        'last_reminder_sent_at' => 'datetime',
        'next_reminder_at' => 'datetime',
        'checklist_items' => 'array',
    ];

    public const STEPS = [
        1 => 'Application',
        2 => 'Documentation',
        3 => 'Commission Setup',
        4 => 'Portal Access',
        5 => 'First Submission',
    ];

    public const STATUSES = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'pending_review' => 'Pending Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function commissionStructure(): BelongsTo
    {
        return $this->belongsTo(CommissionStructure::class, 'assigned_commission_structure_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Scopes
    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending_review');
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeNeedsReminder($query)
    {
        return $query->where('status', 'in_progress')
            ->where('next_reminder_at', '<=', now());
    }

    // Methods
    public function start(): self
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'current_step' => 1,
        ]);

        return $this;
    }

    public function completeStep(int $step, array $data = []): self
    {
        $stepField = "step{$step}_";
        $dataField = "{$stepField}data";
        $completeField = "{$stepField}".$this->getStepFieldName($step).'_complete';
        $completedAtField = "step{$step}_completed_at";

        $this->update([
            $completeField => true,
            $completedAtField => now(),
            $dataField => array_merge($this->{$dataField} ?? [], $data),
        ]);

        $this->calculateProgress();
        $this->advanceToNextStep();

        return $this;
    }

    protected function getStepFieldName(int $step): string
    {
        return match ($step) {
            1 => 'application',
            2 => 'documentation',
            3 => 'commission',
            4 => 'portal',
            5 => 'submission',
            default => 'step',
        };
    }

    public function advanceToNextStep(): self
    {
        if ($this->current_step < 5 && $this->isStepComplete($this->current_step)) {
            $this->update(['current_step' => $this->current_step + 1]);
        }

        if ($this->areAllStepsComplete()) {
            $this->complete();
        }

        return $this;
    }

    public function isStepComplete(int $step): bool
    {
        return match ($step) {
            1 => $this->step1_application_complete,
            2 => $this->step2_documentation_complete,
            3 => $this->step3_commission_complete,
            4 => $this->step4_portal_complete,
            5 => $this->step5_submission_complete,
            default => false,
        };
    }

    public function areAllStepsComplete(): bool
    {
        return $this->step1_application_complete
            && $this->step2_documentation_complete
            && $this->step3_commission_complete
            && $this->step4_portal_complete
            && $this->step5_submission_complete;
    }

    public function calculateProgress(): self
    {
        $completed = 0;
        $total = 5;

        if ($this->step1_application_complete) {
            $completed++;
        }
        if ($this->step2_documentation_complete) {
            $completed++;
        }
        if ($this->step3_commission_complete) {
            $completed++;
        }
        if ($this->step4_portal_complete) {
            $completed++;
        }
        if ($this->step5_submission_complete) {
            $completed++;
        }

        $this->update([
            'completion_percentage' => ($completed / $total) * 100,
        ]);

        return $this;
    }

    public function complete(): self
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completion_percentage' => 100,
        ]);

        $this->supplier->completeOnboarding();

        return $this;
    }

    public function submitForReview(): self
    {
        $this->update(['status' => 'pending_review']);

        return $this;
    }

    public function approve(int $userId, ?string $notes = null): self
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        return $this;
    }

    public function reject(int $userId, string $reason): self
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $this;
    }

    public function cancel(): self
    {
        $this->update(['status' => 'cancelled']);

        return $this;
    }

    public function assignTo(int $userId): self
    {
        $this->update([
            'assigned_to' => $userId,
            'assigned_at' => now(),
        ]);

        return $this;
    }

    public function sendReminder(): self
    {
        $this->update([
            'reminder_count' => $this->reminder_count + 1,
            'last_reminder_sent_at' => now(),
            'next_reminder_at' => now()->addDays(3),
        ]);

        // Trigger reminder notification
        // event(new OnboardingReminderEvent($this));

        return $this;
    }

    public function verifyDocument(string $documentType): self
    {
        $field = match ($documentType) {
            'business_license' => 'business_license_verified',
            'tax_documents' => 'tax_documents_verified',
            'insurance' => 'insurance_verified',
            'identity' => 'identity_verified',
            default => null,
        };

        if ($field) {
            $this->update([$field => true]);

            // Check if all documents are verified
            if ($this->business_license_verified
                && $this->tax_documents_verified
                && $this->insurance_verified
                && $this->identity_verified) {
                $this->completeStep(2);
            }
        }

        return $this;
    }

    public function recordPortalLogin(): self
    {
        if (! $this->portal_first_login) {
            $this->update(['portal_first_login' => now()]);
        }

        return $this;
    }

    public function markTutorialComplete(): self
    {
        $this->update(['tutorial_completed' => true]);

        if ($this->portal_credentials_sent && $this->portal_first_login) {
            $this->completeStep(4);
        }

        return $this;
    }

    public function getProgressStepsAttribute(): array
    {
        return [
            [
                'step' => 1,
                'name' => self::STEPS[1],
                'complete' => $this->step1_application_complete,
                'completed_at' => $this->step1_completed_at,
                'current' => $this->current_step === 1,
            ],
            [
                'step' => 2,
                'name' => self::STEPS[2],
                'complete' => $this->step2_documentation_complete,
                'completed_at' => $this->step2_completed_at,
                'current' => $this->current_step === 2,
            ],
            [
                'step' => 3,
                'name' => self::STEPS[3],
                'complete' => $this->step3_commission_complete,
                'completed_at' => $this->step3_completed_at,
                'current' => $this->current_step === 3,
            ],
            [
                'step' => 4,
                'name' => self::STEPS[4],
                'complete' => $this->step4_portal_complete,
                'completed_at' => $this->step4_completed_at,
                'current' => $this->current_step === 4,
            ],
            [
                'step' => 5,
                'name' => self::STEPS[5],
                'complete' => $this->step5_submission_complete,
                'completed_at' => $this->step5_completed_at,
                'current' => $this->current_step === 5,
            ],
        ];
    }

    // Static Methods
    public static function createForSupplier(Supplier $supplier): self
    {
        return self::create([
            'supplier_id' => $supplier->id,
            'organization_id' => $supplier->organization_id,
            'status' => 'not_started',
            'current_step' => 1,
            'completion_percentage' => 0,
        ]);
    }
}
