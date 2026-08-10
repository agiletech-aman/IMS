<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Department;
use App\Models\SubDepartment;
use App\Support\UniqueCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetController extends Controller
{


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
        $data = $request->validate($this->rules($request, $asset->id));
        if ($request->hasFile('image')) {
            if ($asset->image_path) {
                Storage::disk('public')->delete($asset->image_path);
            }
            $data['image_path'] = $request->file('image')->store('assets', 'public');
        }
        unset($data['image']);
        $asset->update($data);

        return redirect()->route('assets.show', $asset)->with('success', 'Asset updated successfully.');
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
            'brands' => Brand::where('status', 'Active')->orderBy('name')->get(),
            'departments' => Department::where('status', 'Active')->orderBy('name')->get(),
            'subDepartments' => SubDepartment::where('status', 'Active')->orderBy('name')->get(),
        ];
    }

    private function rules(Request $request, ?int $id = null): array
    {
return [
            'name' => ['required', 'string', 'max:255'],
            'asset_type_id' => ['required', 'exists:asset_types,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'sub_department_id' => ['nullable', Rule::exists('sub_departments', 'id')->where('department_id', $request->input('department_id'))],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('assets')->ignore($id)],
            'fr_number' => ['nullable', 'string', 'max:255'],
            'installation_date' => ['nullable', 'date'],
            'cpu' => ['nullable', 'string', 'max:255'],
            'hdd' => ['nullable', 'string', 'max:255'],
            'ram' => ['nullable', 'string', 'max:255'],
            'operating_system' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['Active', 'In Stock', 'Under Maintenance', 'Retired'])],
            'warranty_expiry' => ['nullable', 'date'],
            'amc_expiry' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
