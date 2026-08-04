<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Complaint;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Support\UniqueCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate($this->filterRules());
        $query = $this->filteredQuery($filters)->latest();

        return view('complaints.index', [
            'complaints' => $query->paginate(10)->withQueryString(),
            'statuses' => Complaint::STATUSES,
            'priorities' => Complaint::PRIORITIES,
            'statusCounts' => Complaint::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
        ]);
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $filters = $request->validate($this->filterRules());
        $complaints = $this->filteredQuery($filters)->oldest()->get();

        $audit->record(
            'EXPORT',
            'Complaint Management',
            "Complaints exported to CSV with {$complaints->count()} records.",
            metadata: ['filters' => $filters, 'record_count' => $complaints->count(), 'format' => 'csv'],
        );

        return response()->streamDownload(function () use ($complaints): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Complaint Number', 'Subject', 'Requester', 'Email', 'Contact',
                'Category', 'Priority', 'Related Asset', 'Engineer', 'Status',
                'Raised At', 'Visit Scheduled', 'Resolved At', 'Closed At', 'Resolution Notes',
            ]);

            foreach ($complaints as $complaint) {
                fputcsv($output, [
                    $complaint->complaint_number,
                    $complaint->subject,
                    $complaint->requester_name,
                    $complaint->requester_email,
                    $complaint->requester_contact,
                    $complaint->category,
                    $complaint->priority,
                    $complaint->asset?->asset_tag,
                    $complaint->engineer?->name,
                    $complaint->status,
                    $complaint->created_at->format('Y-m-d H:i:s'),
                    $complaint->visit_scheduled_at?->format('Y-m-d H:i:s'),
                    $complaint->resolved_at?->format('Y-m-d H:i:s'),
                    $complaint->closed_at?->format('Y-m-d H:i:s'),
                    $complaint->resolution_notes,
                ]);
            }
            fclose($output);
        }, 'complaints-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(): View
    {
        return view('complaints.create', [
            'assets' => Asset::orderBy('asset_tag')->get(['id', 'asset_tag', 'name']),
            'categories' => AssetCategory::where('status', 'Active')->orderBy('name')->get(['id', 'name', 'code']),
            'priorities' => Complaint::PRIORITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'requester_name' => ['required', 'string', 'max:255'],
            'requester_email' => ['nullable', 'email', 'max:255'],
            'requester_contact' => ['nullable', 'string', 'max:30'],
            'category' => [
                'required',
                Rule::exists('asset_categories', 'name')->where(fn ($query) => $query->where('status', 'Active')),
            ],
            'priority' => ['required', Rule::in(Complaint::PRIORITIES)],
            'asset_id' => ['nullable', 'exists:assets,id'],
        ]);

        $complaint = DB::transaction(function () use ($data, $request): Complaint {
            $complaint = Complaint::create($data + [
                'complaint_number' => UniqueCodeGenerator::generate('complaints', 'CMP', 'complaints', 'complaint_number'),
                'status' => Complaint::STATUSES[0],
            ]);
            $complaint->activities()->create([
                'to_status' => Complaint::STATUSES[0],
                'performed_by' => $this->actor($request),
                'note' => 'Complaint was raised.',
            ]);

            return $complaint;
        });

        $this->notify($complaint, 'New complaint raised', "{$complaint->complaint_number}: {$complaint->subject}");

        return redirect()->route('complaints.show', $complaint)
            ->with('success', 'Complaint raised successfully.');
    }

    public function show(Complaint $complaint): View
    {
        return view('complaints.show', [
            'complaint' => $complaint->load(['asset', 'engineer', 'activities']),
            'engineers' => User::where('status', 'Active')->orderBy('name')->get(['id', 'name', 'role']),
            'statuses' => Complaint::STATUSES,
        ]);
    }

    public function assign(Request $request, Complaint $complaint): RedirectResponse
    {
        if (in_array($complaint->status, ['Resolved', 'Closed'], true)) {
            throw ValidationException::withMessages(['engineer_id' => 'A resolved or closed complaint cannot be reassigned.']);
        }

        $data = $request->validate([
            'engineer_id' => ['required', 'exists:users,id'],
            'visit_scheduled_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $engineer = User::findOrFail($data['engineer_id']);
        $fromStatus = $complaint->status;
        $toStatus = $fromStatus === 'Complaint Raised' ? 'Engineer Assigned' : $fromStatus;

        DB::transaction(function () use ($complaint, $data, $engineer, $fromStatus, $toStatus, $request): void {
            $complaint->update([
                'engineer_id' => $engineer->id,
                'visit_scheduled_at' => $data['visit_scheduled_at'] ?? null,
                'status' => $toStatus,
                'assigned_at' => $complaint->assigned_at ?? now(),
            ]);
            $complaint->activities()->create([
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'performed_by' => $this->actor($request),
                'note' => ($data['note'] ?? null) ?: "{$engineer->name} was assigned as engineer.",
            ]);
        });

        $this->notify($complaint->fresh(), 'Engineer assigned', "{$engineer->name} was assigned to {$complaint->complaint_number}.");

        return back()->with('success', 'Engineer assigned successfully.');
    }

    public function updateStatus(Request $request, Complaint $complaint): RedirectResponse
    {
        $fromStatus = $complaint->status;
        $nextStatus = $complaint->nextStatus();
        $data = $request->validate([
            'status' => ['required', Rule::in(Complaint::STATUSES)],
            'note' => ['nullable', 'string', 'max:5000'],
            'resolution_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        if (! $nextStatus || $data['status'] !== $nextStatus) {
            throw ValidationException::withMessages([
                'status' => $nextStatus
                    ? "The next valid stage is {$nextStatus}."
                    : 'This complaint is already closed.',
            ]);
        }
        if (! $complaint->engineer_id) {
            throw ValidationException::withMessages(['status' => 'Assign an engineer before progressing this complaint.']);
        }
        if ($nextStatus === 'Resolved' && blank($data['resolution_notes'] ?? null)) {
            throw ValidationException::withMessages(['resolution_notes' => 'Resolution notes are required before resolving a complaint.']);
        }

        $timestampColumn = [
            'Engineer Visit' => 'visit_started_at',
            'Work in Progress' => 'work_started_at',
            'Resolved' => 'resolved_at',
            'Closed' => 'closed_at',
        ][$nextStatus] ?? null;

        DB::transaction(function () use ($complaint, $data, $fromStatus, $nextStatus, $timestampColumn, $request): void {
            $updates = ['status' => $nextStatus];
            if ($timestampColumn) {
                $updates[$timestampColumn] = now();
            }
            if ($nextStatus === 'Resolved') {
                $updates['resolution_notes'] = $data['resolution_notes'];
            }
            $complaint->update($updates);
            $complaint->activities()->create([
                'from_status' => $fromStatus,
                'to_status' => $nextStatus,
                'performed_by' => $this->actor($request),
                'note' => ($data['note'] ?? null) ?: "Complaint moved to {$nextStatus}.",
            ]);
        });

        $this->notify($complaint->fresh(), 'Complaint status updated', "{$complaint->complaint_number} moved to {$nextStatus}.");

        return back()->with('success', "Complaint moved to {$nextStatus}.");
    }

    public function destroy(Complaint $complaint): RedirectResponse
    {
        $number = $complaint->complaint_number;
        $complaint->delete();

        return redirect()->route('complaints.index')
            ->with('success', "{$number} deleted successfully.");
    }

    private function actor(Request $request): string
    {
        return (string) $request->session()->get('static_auth_user.name', 'System');
    }

    private function notify(Complaint $complaint, string $title, string $message): void
    {
        $this->notifications->send(
            'complaint_changed',
            $title,
            $message,
            $complaint->priority === 'Critical' ? 'danger' : 'info',
            'Complaint Management',
            ['complaint_id' => $complaint->id],
        );
    }

    private function filteredQuery(array $filters): Builder
    {
        return Complaint::with(['engineer', 'asset'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder->where('complaint_number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('requester_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority));
    }

    private function filterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(Complaint::STATUSES)],
            'priority' => ['nullable', Rule::in(Complaint::PRIORITIES)],
        ];
    }
}
