<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
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
    public function index(Request $request): View
    {
        $query = Asset::with(['category', 'type', 'brand', 'department']);
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('asset_tag', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")->orWhere('serial_number', 'like', "%{$search}%"));
        }

        return view('assets.index', [
            'assets' => $query->latest()->paginate(10)->withQueryString(),
            'stats' => [
                'total' => Asset::count(),
                'assigned' => Asset::whereNotNull('assigned_to')->count(),
                'stock' => Asset::where('status', 'In Stock')->count(),
                'maintenance' => Asset::where('status', 'Under Maintenance')->count(),
            ],
        ]);
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
        return view('assets.show', ['asset' => $asset->load(['category', 'type', 'brand', 'department', 'subDepartment'])]);
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
            'categories' => AssetCategory::where('status', 'Active')->orderBy('name')->get(),
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
            'asset_category_id' => ['required', 'exists:asset_categories,id'],
            'asset_type_id' => ['required', Rule::exists('asset_types', 'id')->where('asset_category_id', $request->input('asset_category_id'))],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'sub_department_id' => ['nullable', Rule::exists('sub_departments', 'id')->where('department_id', $request->input('department_id'))],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('assets')->ignore($id)],
            'purchase_date' => ['nullable', 'date'],
            'installation_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['Active', 'In Stock', 'Under Maintenance', 'Retired'])],
            'warranty_expiry' => ['nullable', 'date'],
            'amc_expiry' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
