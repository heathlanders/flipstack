<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier\SupplierProductSubmission;
use App\Services\Supplier\SupplierUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SupplierSubmissionController extends Controller
{
    public function __construct(protected SupplierUploadService $uploadService)
    {
        $this->middleware(['auth']);
    }

    /**
     * List all submissions for the organization.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $query = SupplierProductSubmission::where('organization_id', $organizationId)
            ->with(['supplier:id,name,company_name', 'reviewer:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status') && in_array($request->input('status'), ['pending_review', 'approved', 'rejected'])) {
            $query->where('status', $request->input('status'));
        }

        $submissions = $query->paginate(20)->withQueryString();

        $stats = $this->uploadService->getSubmissionStats($organizationId);

        return Inertia::render('Admin/SupplierSubmissions/Index', [
            'submissions' => $submissions,
            'stats' => $stats,
            'filters' => [
                'status' => $request->input('status', ''),
            ],
        ]);
    }

    /**
     * Show a submission for review.
     */
    public function review(SupplierProductSubmission $submission)
    {
        $submission->load(['supplier:id,name,company_name,email,phone', 'reviewer:id,name']);

        return Inertia::render('Admin/SupplierSubmissions/Review', [
            'submission' => $submission,
        ]);
    }

    /**
     * Approve a submission.
     */
    public function approve(Request $request, SupplierProductSubmission $submission)
    {
        $this->uploadService->approve($submission, Auth::id());

        return redirect()->route('admin.supplier-submissions.index')
            ->with('success', 'Submission approved and added to intake queue.');
    }

    /**
     * Reject a submission.
     */
    public function reject(Request $request, SupplierProductSubmission $submission)
    {
        $request->validate([
            'review_notes' => 'required|string|max:2000',
        ]);

        $this->uploadService->reject($submission, Auth::id(), $request->input('review_notes'));

        return redirect()->route('admin.supplier-submissions.index')
            ->with('success', 'Submission rejected.');
    }
}
