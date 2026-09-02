<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAssignmentHistory;
use App\Models\AssetSubtype;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\SubDepartment;
use App\Services\NotificationService;
use App\Support\UniqueCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}


public function index(Request $request)
{
    $query = Asset::query()
        ->with([
            'type',
            'department',
        ]);

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    if ($request->filled('search')) {

        $search = trim($request->search);

        $query->where(function ($q) use ($search) {

            $q->where('name', 'like', "%{$search}%")
                ->orWhere('asset_tag', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%")
                ->orWhere('fr_number', 'like', "%{$search}%");

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Asset Type
    |--------------------------------------------------------------------------
    */

    if ($request->filled('type')) {

        $query->where(
            'asset_type_id',
            $request->type
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Department
    |--------------------------------------------------------------------------
    */

    if ($request->filled('department')) {

        $query->where(
            'department_id',
            $request->department
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    if ($request->filled('status')) {

        $query->where(
            'status',
            $request->status
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Installation Date
    |--------------------------------------------------------------------------
    */

    if ($request->filled('date_from')) {

        $query->whereDate(
            'installation_date',
            '>=',
            $request->date_from
        );

    }


    if ($request->filled('date_to')) {

        $query->whereDate(
            'installation_date',
            '<=',
            $request->date_to
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Assignment
    |--------------------------------------------------------------------------
    */

    if ($request->filled('assigned')) {

        if ($request->assigned === '1') {
            $query->whereNotNull('assigned_to')->where('assigned_to', '!=', '');
        } else {
            $query->where(function ($q) {
                $q->whereNull('assigned_to')->orWhere('assigned_to', '');
            });
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Coverage Due (30 days)
    |--------------------------------------------------------------------------
    */

    if (in_array($request->coverage_due, ['amc', 'warranty'], true)) {

        $column = $request->coverage_due === 'amc' ? 'amc_expiry' : 'warranty_expiry';

        $query->whereBetween($column, [today(), today()->addDays(30)]);

    }


    /*
    |--------------------------------------------------------------------------
    | Sorting
    |--------------------------------------------------------------------------
    */

    $allowedSorts = [
        'name',
        'serial_number',
        'installation_date',
        'created_at',
    ];


    $sort = in_array(
        $request->sort,
        $allowedSorts,
        true
    )
        ? $request->sort
        : 'created_at';


    $direction = $request->direction === 'asc'
        ? 'asc'
        : 'desc';


    $query->orderBy(
        $sort,
        $direction
    );


    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

$perPage = (int) $request->input('per_page', 10);

if (!in_array($perPage, [10, 25, 50, 100], true)) {
    $perPage = 10;
}

$assets = $query
    ->paginate($perPage)
    ->withQueryString();


    /*
    |--------------------------------------------------------------------------
    | Filter dropdown data
    |--------------------------------------------------------------------------
    */

    $assetTypes = AssetType::query()
        ->orderBy('name')
        ->get();


    $departments = Department::query()
        ->orderBy('name')
        ->get();


    /*
    |--------------------------------------------------------------------------
    | Stats
    |--------------------------------------------------------------------------
    */

    $stats = [
        'total' => Asset::count(),

        'assigned' => Asset::whereNotNull(
            'assigned_to'
        )->count(),

        'stock' => Asset::where(
            'status',
            'In Stock'
        )->count(),

        'maintenance' => Asset::where(
            'status',
            'Under Maintenance'
        )->count(),
    ];


    return view(
        'assets.index',
        compact(
            'assets',
            'stats',
            'assetTypes',
            'departments'
        )
    );
}

    public function create(): View
    {
        return view('assets.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request));
        $data['subtype_values'] = $this->subtypeValues($request);
        $characters = Str::of($data['name'])->ascii()->upper()->replaceMatches('/[^A-Z0-9]/', '')->value();
        $first = $characters[0] ?? 'X';
        $last = $characters !== '' ? $characters[strlen($characters) - 1] : 'X';
        $assetPrefix = 'AST-'.$first.$last;
        $data['asset_tag'] = UniqueCodeGenerator::generate('assets:'.$first.$last, $assetPrefix, 'assets', 'asset_tag');
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('assets', 'public');
        }
        unset($data['image']);
        $asset = Asset::create($data);

        return redirect()->route('assets.show', $asset)->with('success', 'Asset created successfully.');
    }

public function show(Asset $asset): View
    {
        return view('assets.show', ['asset' => $asset->load(['type', 'brand', 'department', 'subDepartment'])]);
    }

    public function edit(Asset $asset): View
    {
        return view('assets.edit', $this->formData() + ['asset' => $asset]);
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $previousAssignedTo = $asset->assigned_to;

        $data = $request->validate($this->rules($request, $asset->id));
        $data['subtype_values'] = $this->subtypeValues($request);
        if ($request->hasFile('image')) {
            if ($asset->image_path) {
                Storage::disk('public')->delete($asset->image_path);
            }
            $data['image_path'] = $request->file('image')->store('assets', 'public');
        }
        unset($data['image']);
        $asset->update($data);

        $this->syncAssignment($asset, $previousAssignedTo);

        return redirect()->route('assets.show', $asset)->with('success', 'Asset updated successfully.');
    }

    /**
     * When the asset form's "Assigned To" field changes, mirror the same side
     * effects UserController::assignAsset() applies: close/open the assignment
     * history trail and notify. A generic UPDATE audit entry (with the
     * assigned_to diff) is already recorded by AuditObserver on every save.
     */
    private function syncAssignment(Asset $asset, ?string $previousAssignedTo): void
    {
        $previous = trim((string) $previousAssignedTo);
        $current = trim((string) $asset->assigned_to);

        if ($previous === $current) {
            return;
        }

        $actor = session('static_auth_user.name') ?? session('static_auth_user.email') ?? 'System';

        if ($previous !== '') {
            AssetAssignmentHistory::where('asset_id', $asset->id)
                ->whereNull('unassigned_at')
                ->latest('assigned_at')
                ->first()
                ?->update(['unassigned_at' => now(), 'unassigned_by' => $actor]);
        }

        if ($current === '') {
            return;
        }

        if ($faculty = Faculty::where('name', $current)->first()) {
            AssetAssignmentHistory::create([
                'faculty_id' => $faculty->id,
                'asset_id' => $asset->id,
                'assigned_at' => now(),
                'assigned_by' => $actor,
            ]);
        }

        $this->notifications->send(
            'asset_assigned',
            'Asset assigned',
            "{$asset->asset_tag} — {$asset->name} was assigned to {$current}.",
            'success',
            'Assets',
            ['asset_id' => $asset->id],
        );
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        if ($asset->image_path) {
            Storage::disk('public')->delete($asset->image_path);
        }
        $asset->delete();

        return redirect()->route('assets.index')->with('success', 'Asset deleted successfully.');
    }

    private function formData(): array
    {
return [
            'types' => AssetType::where('status', 'Active')->orderBy('name')->get(),
            'subtypes' => AssetSubtype::where('status', 'Active')->orderBy('name')->get(['id', 'asset_type_id', 'name']),
            'brands' => Brand::where('status', 'Active')->orderBy('name')->get(),
            'departments' => Department::where('status', 'Active')->orderBy('name')->get(),
            'subDepartments' => SubDepartment::where('status', 'Active')->orderBy('name')->get(),
            'users' => Faculty::where('status', 'Active')->orderBy('name')->get(['id', 'name', 'unique_id']),
        ];
    }

    private function rules(Request $request, ?int $id = null): array
    {
$rules = [
            'name' => ['required', 'string', 'max:255'],
            'asset_type_id' => ['required', 'exists:asset_types,id'],
            'brand_id' => ['required', 'exists:brands,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'sub_department_id' => ['required', Rule::exists('sub_departments', 'id')->where('department_id', $request->input('department_id'))],
            'serial_number' => ['required', 'string', 'max:255', Rule::unique('assets')->ignore($id)],
            'fr_number' => ['required', 'string', 'max:255'],
            'installation_date' => ['required', 'date'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['Active', 'In Stock', 'Under Maintenance', 'Retired'])],
            'warranty_expiry' => ['nullable', 'date'],
            'amc_expiry' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        foreach ($this->subtypeIdsForType($request) as $subtypeId) {
            $subtype = AssetSubtype::find($subtypeId);
            $rules["subtype_values.{$subtypeId}"] = ($subtype?->is_required ?? true)
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * The subtype field values submitted with the request, filtered down to only
     * the fields that actually belong to the selected asset type.
     */
    private function subtypeValues(Request $request): array
    {
        $allowedIds = $this->subtypeIdsForType($request)->map(fn (int $id) => (string) $id);

        return collect($request->input('subtype_values', []))
            ->only($allowedIds)
            ->all();
    }

    private function subtypeIdsForType(Request $request): \Illuminate\Support\Collection
    {
        return AssetSubtype::where('asset_type_id', $request->input('asset_type_id'))
            ->where('status', 'Active')
            ->pluck('id');
    }
}
