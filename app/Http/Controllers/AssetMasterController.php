<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Department;
use App\Models\SubDepartment;
use App\Support\UniqueCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetMasterController extends Controller
{
    public function index(Request $request): View
    {
        $module = $this->module($request);
        $config = $this->config($module);
        $query = $config['model']::query()->with($config['with'] ?? [])->withCount($config['counts'] ?? []);

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
        }

        return view('asset-masters.index', [
            ...$config,
            'module' => $module,
            'records' => $query->latest()->paginate(10)->withQueryString(),
            'options' => $this->options($module),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $module = $this->module($request);
        $config = $this->config($module);
        $data = $request->validate($this->rules($module));
        $table = $config['model']::make()->getTable();
        $data['code'] = UniqueCodeGenerator::generate($module, $config['prefix'], $table, 'code');

        if ($module === 'brands' && $request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('brands', 'public');
        }
        unset($data['logo']);
        $config['model']::create($data);

        return back()->with('success', $config['singular'].' created successfully.');
    }

    public function update(Request $request, int $record): RedirectResponse
    {
        $module = $this->module($request);
        $config = $this->config($module);
        $model = $config['model']::findOrFail($record);
        $data = $request->validate($this->rules($module, $model->id));

        if ($module === 'brands' && $request->hasFile('logo')) {
            if ($model->logo_path) {
                Storage::disk('public')->delete($model->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('brands', 'public');
        }
        unset($data['logo']);
        $model->update($data);

        return back()->with('success', $config['singular'].' updated successfully.');
    }

    public function destroy(Request $request, int $record): RedirectResponse
    {
        $module = $this->module($request);
        $config = $this->config($module);
        $model = $config['model']::findOrFail($record);

        try {
            $logo = $module === 'brands' ? $model->logo_path : null;
            $model->delete();
            if ($logo) {
                Storage::disk('public')->delete($logo);
            }
        } catch (QueryException) {
            return back()->with('error', 'This '.$config['singular'].' is in use and cannot be deleted.');
        }

        return back()->with('success', $config['singular'].' deleted successfully.');
    }

    private function module(Request $request): string
    {
        return explode('.', (string) $request->route()->getName())[1];
    }

    private function config(string $module): array
    {
        return match ($module) {
            'departments' => ['model' => Department::class, 'prefix' => 'DT', 'title' => 'Departments', 'singular' => 'Department', 'description' => 'Manage the departments responsible for company assets.', 'icon' => 'fa-building', 'counts' => ['subDepartments', 'assets']],
            'sub-departments' => ['model' => SubDepartment::class, 'prefix' => 'ST', 'title' => 'Sub Departments', 'singular' => 'Sub Department', 'description' => 'Organize asset ownership within each department.', 'icon' => 'fa-sitemap', 'with' => ['department'], 'counts' => ['assets']],
            'types' => ['model' => AssetType::class, 'prefix' => 'TE', 'title' => 'Asset Types', 'singular' => 'Type', 'description' => 'Define asset types used throughout the inventory.', 'icon' => 'fa-shapes', 'with' => ['category'], 'counts' => ['assets']],
            'brands' => ['model' => Brand::class, 'prefix' => 'BD', 'title' => 'Brands', 'singular' => 'Brand', 'description' => 'Maintain approved manufacturers and brand logos.', 'icon' => 'fa-copyright', 'counts' => ['assets']],
            'categories' => ['model' => AssetCategory::class, 'prefix' => 'CY', 'title' => 'Asset Categories', 'singular' => 'Category', 'description' => 'Group related asset types into clear categories.', 'icon' => 'fa-tags', 'counts' => ['types', 'assets']],
            default => abort(404),
        };
    }

    private function options(string $module): array
    {
        return match ($module) {
            'sub-departments' => ['department_id' => Department::orderBy('name')->pluck('name', 'id')],
            'types' => ['asset_category_id' => AssetCategory::orderBy('name')->pluck('name', 'id')],
            default => [],
        };
    }

    private function rules(string $module, ?int $id = null): array
    {
        $common = [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];

        return match ($module) {
            'departments' => $common + ['description' => ['nullable', 'string', 'max:2000']],
            'sub-departments' => $common + ['department_id' => ['required', 'exists:departments,id'], 'description' => ['nullable', 'string', 'max:2000']],
            'types' => $common + ['asset_category_id' => ['required', 'exists:asset_categories,id'], 'description' => ['nullable', 'string', 'max:2000']],
            'brands' => $common + ['country' => ['nullable', 'string', 'max:100'], 'support_contact' => ['nullable', 'string', 'max:255'], 'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']],
            'categories' => $common + ['description' => ['nullable', 'string', 'max:2000']],
            default => [],
        };
    }
}
