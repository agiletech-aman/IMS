<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Models\AssetSubtype;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Department;
use App\Models\SubDepartment;
use App\Support\UniqueCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetMasterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): View
    {
        $module = $this->module($request);

        $config = $this->config($module);

        $query = $config['model']::query()
            ->with($config['with'] ?? [])
            ->withCount($config['counts'] ?? []);


        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($search = trim((string) $request->query('search'))) {

            $query->where(function ($q) use ($search) {

                $q->where(
                    'name',
                    'like',
                    "%{$search}%"
                )
                ->orWhere(
                    'code',
                    'like',
                    "%{$search}%"
                );

            });

        }


        /*
        |--------------------------------------------------------------------------
        | Per Page
        |--------------------------------------------------------------------------
        */

        $perPage = (int) $request->query(
            'per_page',
            10
        );

        if (!in_array(
            $perPage,
            [10, 25, 50, 100],
            true
        )) {
            $perPage = 10;
        }


        /*
        |--------------------------------------------------------------------------
        | Records
        |--------------------------------------------------------------------------
        */

        $records = $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();


        return view('asset-masters.index', [

            ...$config,

            'module' => $module,

            'records' => $records,

            'options' => $this->options($module),

        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | EXPORT
    |--------------------------------------------------------------------------
    */

    public function export(Request $request)
    {
        $module = $this->module($request);

        $config = $this->config($module);


        $query = $config['model']::query()
            ->with($config['with'] ?? [])
            ->withCount($config['counts'] ?? []);


        /*
        |--------------------------------------------------------------------------
        | Preserve Search Filter
        |--------------------------------------------------------------------------
        */

        if ($search = trim((string) $request->query('search'))) {

            $query->where(function ($q) use ($search) {

                $q->where(
                    'name',
                    'like',
                    "%{$search}%"
                )
                ->orWhere(
                    'code',
                    'like',
                    "%{$search}%"
                );

            });

        }


        $records = $query
            ->orderBy('name')
            ->get();


        $filename =
            $module
            . '-'
            . now()->format('Y-m-d-His')
            . '.csv';


        return response()->streamDownload(
            function () use (
                $records,
                $module
            ) {

                $handle = fopen(
                    'php://output',
                    'w'
                );


                /*
                |--------------------------------------------------------------------------
                | UTF-8 BOM for Excel
                |--------------------------------------------------------------------------
                */

                fwrite(
                    $handle,
                    "\xEF\xBB\xBF"
                );


                /*
                |--------------------------------------------------------------------------
                | Header
                |--------------------------------------------------------------------------
                */

                fputcsv(
                    $handle,
                    $this->exportHeaders($module)
                );


                /*
                |--------------------------------------------------------------------------
                | Rows
                |--------------------------------------------------------------------------
                */

                foreach ($records as $record) {

                    fputcsv(
                        $handle,
                        $this->exportRow(
                            $module,
                            $record
                        )
                    );

                }


                fclose($handle);

            },
            $filename,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | IMPORT
    |--------------------------------------------------------------------------
    */

    public function import(
        Request $request
    ): RedirectResponse
    {
        $module = $this->module($request);

        $config = $this->config($module);


        /*
        |--------------------------------------------------------------------------
        | Validate File
        |--------------------------------------------------------------------------
        */

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:5120',
            ],
        ]);


        $path = $request
            ->file('file')
            ->getRealPath();


        $handle = fopen(
            $path,
            'r'
        );


        if (!$handle) {

            return back()->with(
                'error',
                'Unable to read the uploaded CSV file.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Read Header
        |--------------------------------------------------------------------------
        */

        $header = fgetcsv($handle);


        if (!$header) {

            fclose($handle);

            return back()->with(
                'error',
                'CSV file is empty.'
            );

        }


        $header = array_map(
            function ($value) {

                $value = preg_replace(
                    '/^\xEF\xBB\xBF/',
                    '',
                    (string) $value
                );

                return strtolower(
                    trim($value)
                );

            },
            $header
        );


        if (!in_array(
            'name',
            $header,
            true
        )) {

            fclose($handle);

            return back()->with(
                'error',
                'CSV must contain a name column.'
            );

        }


        $imported = 0;
        $skipped = 0;


        DB::beginTransaction();


        try {

            while (
                ($row = fgetcsv($handle))
                !== false
            ) {

                /*
                |--------------------------------------------------------------------------
                | Skip Empty Rows
                |--------------------------------------------------------------------------
                */

                if (
                    count(
                        array_filter(
                            $row,
                            fn ($value) =>
                                trim((string) $value) !== ''
                        )
                    ) === 0
                ) {
                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Invalid Column Count
                |--------------------------------------------------------------------------
                */

                if (
                    count($row)
                    !== count($header)
                ) {

                    $skipped++;

                    continue;

                }


                $data = array_combine(
                    $header,
                    $row
                );


                if (!$data) {

                    $skipped++;

                    continue;

                }


                $name = trim(
                    (string) (
                        $data['name']
                        ?? ''
                    )
                );


                if ($name === '') {

                    $skipped++;

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Prepare Module Data
                |--------------------------------------------------------------------------
                */

                $modelData = $this->prepareImportData(
                    $module,
                    $data
                );


                if ($modelData === null) {

                    $skipped++;

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Find Existing By Name
                |--------------------------------------------------------------------------
                */

                $model = $config['model']::query()
                    ->whereRaw(
                        'LOWER(name) = ?',
                        [strtolower($name)]
                    )
                    ->first();


                /*
                |--------------------------------------------------------------------------
                | Update Existing
                |--------------------------------------------------------------------------
                */

                if ($model) {

                    $model->update(
                        $modelData
                    );

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Generate Code
                    |--------------------------------------------------------------------------
                    */

                    $table = $config['model']::make()
                        ->getTable();


                    $modelData['code'] =
                        UniqueCodeGenerator::generate(
                            $module,
                            $config['prefix'],
                            $table,
                            'code'
                        );


                    $config['model']::create(
                        $modelData
                    );

                }


                $imported++;

            }


            DB::commit();


        } catch (\Throwable $exception) {

            DB::rollBack();

            fclose($handle);


            report($exception);


            return back()->with(
                'error',
                'Import failed. No changes were saved.'
            );

        }


        fclose($handle);


        return back()->with(
            'success',
            $imported
            . ' records imported successfully.'
            . (
                $skipped > 0
                    ? ' '.$skipped.' rows skipped.'
                    : ''
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): RedirectResponse
    {
        $module = $this->module($request);

        $config = $this->config($module);


        $data = $request->validate(
            $this->rules($module)
        );


        $table = $config['model']::make()
            ->getTable();


        $data['code'] =
            UniqueCodeGenerator::generate(
                $module,
                $config['prefix'],
                $table,
                'code'
            );


        /*
        |--------------------------------------------------------------------------
        | Brand Logo
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'brands'
            &&
            $request->hasFile('logo')
        ) {

            $data['logo_path'] =
                $request
                    ->file('logo')
                    ->store(
                        'brands',
                        'public'
                    );

        }


        unset($data['logo']);


        $config['model']::create(
            $data
        );


        return back()->with(
            'success',
            $config['singular']
            .' created successfully.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        int $record
    ): RedirectResponse
    {
        $module = $this->module($request);

        $config = $this->config($module);


        $model = $config['model']::findOrFail(
            $record
        );


        $data = $request->validate(
            $this->rules(
                $module,
                $model->id
            )
        );


        /*
        |--------------------------------------------------------------------------
        | Brand Logo
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'brands'
            &&
            $request->hasFile('logo')
        ) {

            if ($model->logo_path) {

                Storage::disk('public')
                    ->delete(
                        $model->logo_path
                    );

            }


            $data['logo_path'] =
                $request
                    ->file('logo')
                    ->store(
                        'brands',
                        'public'
                    );

        }


        unset($data['logo']);


        $model->update(
            $data
        );


        return back()->with(
            'success',
            $config['singular']
            .' updated successfully.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        int $record
    ): RedirectResponse
    {
        $module = $this->module($request);

        $config = $this->config($module);


        $model = $config['model']::findOrFail(
            $record
        );


        try {

            $logo =
                $module === 'brands'
                    ? $model->logo_path
                    : null;


            $model->delete();


            if ($logo) {

                Storage::disk('public')
                    ->delete($logo);

            }

        } catch (QueryException) {

            return back()->with(
                'error',
                'This '
                .$config['singular']
                .' is in use and cannot be deleted.'
            );

        }


        return back()->with(
            'success',
            $config['singular']
            .' deleted successfully.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | IMPORT DATA MAPPING
    |--------------------------------------------------------------------------
    */

    private function prepareImportData(
        string $module,
        array $data
    ): ?array
    {
        $name = trim(
            (string) (
                $data['name']
                ?? ''
            )
        );


        $status = trim(
            (string) (
                $data['status']
                ?? 'Active'
            )
        );


        if (!in_array(
            $status,
            [
                'Active',
                'Inactive',
            ],
            true
        )) {
            $status = 'Active';
        }


        $common = [
            'name' => $name,
            'status' => $status,
        ];


        return match ($module) {

            /*
            |--------------------------------------------------------------------------
            | Departments
            |--------------------------------------------------------------------------
            */

            'departments' =>
                $common + [
                    'description' =>
                        $this->nullableString(
                            $data['description']
                            ?? null
                        ),
                ],


            /*
            |--------------------------------------------------------------------------
            | Sub Departments
            |--------------------------------------------------------------------------
            */

            'sub-departments' =>
                $this->subDepartmentImportData(
                    $common,
                    $data
                ),


            /*
            |--------------------------------------------------------------------------
            | Types
            |--------------------------------------------------------------------------
            */

            'types' =>
                $this->typeImportData(
                    $common,
                    $data
                ),


            /*
            |--------------------------------------------------------------------------
            | Sub Types
            |--------------------------------------------------------------------------
            */

            'sub-types' =>
                $this->subtypeImportData(
                    $common,
                    $data
                ),


            /*
            |--------------------------------------------------------------------------
            | Brands
            |--------------------------------------------------------------------------
            */

            'brands' =>
                $common + [
                    'country' =>
                        $this->nullableString(
                            $data['country']
                            ?? null
                        ),

                    'support_contact' =>
                        $this->nullableString(
                            $data['support_contact']
                            ?? null
                        ),
                ],


            /*
            |--------------------------------------------------------------------------
            | Categories
            |--------------------------------------------------------------------------
            */

            'categories' =>
                $common + [
                    'description' =>
                        $this->nullableString(
                            $data['description']
                            ?? null
                        ),
                ],


            default => null,
        };
    }


    /*
    |--------------------------------------------------------------------------
    | SUB DEPARTMENT IMPORT
    |--------------------------------------------------------------------------
    */

    private function subDepartmentImportData(
        array $common,
        array $data
    ): ?array
    {
        $departmentName = trim(
            (string) (
                $data['department']
                ?? ''
            )
        );


        if ($departmentName === '') {
            return null;
        }


        $department = Department::query()
            ->whereRaw(
                'LOWER(name) = ?',
                [strtolower($departmentName)]
            )
            ->first();


        if (!$department) {
            return null;
        }


        return $common + [

            'department_id' =>
                $department->id,

            'description' =>
                $this->nullableString(
                    $data['description']
                    ?? null
                ),

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | TYPE IMPORT
    |--------------------------------------------------------------------------
    */

    private function typeImportData(
        array $common,
        array $data
    ): ?array
    {
        return $common + [
            'description' =>
                $this->nullableString(
                    $data['description']
                    ?? null
                ),

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | SUB TYPE IMPORT
    |--------------------------------------------------------------------------
    */

    private function subtypeImportData(
        array $common,
        array $data
    ): ?array
    {
        $assetTypeName = trim(
            (string) (
                $data['asset_type']
                ?? ''
            )
        );


        if ($assetTypeName === '') {
            return null;
        }


        $assetType = AssetType::query()
            ->whereRaw(
                'LOWER(name) = ?',
                [strtolower($assetTypeName)]
            )
            ->first();


        if (!$assetType) {
            return null;
        }


        return $common + [

            'asset_type_id' =>
                $assetType->id,
            'is_required' =>
                (bool) filter_var(
                    $data['is_required'] ?? true,
                    FILTER_VALIDATE_BOOL,
                ),
            'description' =>
                $this->nullableString(
                    $data['description']
                    ?? null
                ),

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | EXPORT HEADERS
    |--------------------------------------------------------------------------
    */

    private function exportHeaders(
        string $module
    ): array
    {
        return match ($module) {

            'departments' => [
                'name',
                'code',
                'status',
                'description',
                'sub_departments',
                'assets',
            ],

            'sub-departments' => [
                'name',
                'code',
                'department',
                'status',
                'description',
                'assets',
            ],

            'types' => [
                'name',
                'code',
                'status',
                'description',
                'assets',
            ],

            'sub-types' => [
                'name',
                'code',
                'asset_type',
                'status',
                'description',
                'assets',
            ],

            'brands' => [
                'name',
                'code',
                'country',
                'support_contact',
                'status',
                'assets',
            ],

            'categories' => [
                'name',
                'code',
                'status',
                'description',
                'asset_types',
                'assets',
            ],

            default => [],
        };
    }


    /*
    |--------------------------------------------------------------------------
    | EXPORT ROW
    |--------------------------------------------------------------------------
    */

    private function exportRow(
        string $module,
        $record
    ): array
    {
        return match ($module) {

            'departments' => [
                $record->name,
                $record->code,
                $record->status,
                $record->description,
                $record->sub_departments_count,
                $record->assets_count,
            ],

            'sub-departments' => [
                $record->name,
                $record->code,
                $record->department?->name,
                $record->status,
                $record->description,
                $record->assets_count,
            ],

            'types' => [
                $record->name,
                $record->code,
                $record->status,
                $record->description,
                $record->assets_count,
            ],

            'sub-types' => [
                $record->name,
                $record->code,
                $record->assetType?->name,
                $record->status,
                $record->description,
                $record->assets_count,
            ],

            'brands' => [
                $record->name,
                $record->code,
                $record->country,
                $record->support_contact,
                $record->status,
                $record->assets_count,
            ],

            'categories' => [
                $record->name,
                $record->code,
                $record->status,
                $record->description,
                $record->types_count,
                $record->assets_count,
            ],

            default => [],
        };
    }


    /*
    |--------------------------------------------------------------------------
    | NULLABLE STRING
    |--------------------------------------------------------------------------
    */

    private function nullableString(
        mixed $value
    ): ?string
    {
        $value = trim(
            (string) $value
        );


        return $value !== ''
            ? $value
            : null;
    }


    /*
    |--------------------------------------------------------------------------
    | MODULE
    |--------------------------------------------------------------------------
    */

    private function module(
        Request $request
    ): string
    {
        return explode(
            '.',
            (string) $request
                ->route()
                ->getName()
        )[1];
    }


    /*
    |--------------------------------------------------------------------------
    | CONFIG
    |--------------------------------------------------------------------------
    */

    private function config(
        string $module
    ): array
    {
        return match ($module) {

            'departments' => [
                'model' => Department::class,
                'prefix' => 'DT',
                'title' => 'Departments',
                'singular' => 'Department',
                'description' =>
                    'Manage the departments responsible for company assets.',
                'icon' => 'fa-building',
                'counts' => [
                    'subDepartments',
                    'assets',
                ],
            ],


            'sub-departments' => [
                'model' => SubDepartment::class,
                'prefix' => 'ST',
                'title' => 'Sub Departments',
                'singular' => 'Sub Department',
                'description' =>
                    'Organize asset ownership within each department.',
                'icon' => 'fa-sitemap',
                'with' => [
                    'department',
                ],
                'counts' => [
                    'assets',
                ],
            ],


            'types' => [
                'model' => AssetType::class,
                'prefix' => 'TE',
                'title' => 'Asset Types',
                'singular' => 'Type',
                'description' =>
                    'Define asset types used throughout the inventory.',
                'icon' => 'fa-shapes',
                'counts' => [
                    'assets',
                ],
            ],


            'sub-types' => [
                'model' => AssetSubtype::class,
                'prefix' => 'SY',
                'title' => 'Asset Subtypes',
                'singular' => 'Subtype',
                'description' =>
                    'Define subtypes within each asset type for finer classification.',
                'icon' => 'fa-layer-group',
                'with' => [
                    'assetType',
                ],
            ],


            'brands' => [
                'model' => Brand::class,
                'prefix' => 'BD',
                'title' => 'Brands',
                'singular' => 'Brand',
                'description' =>
                    'Maintain approved manufacturers and brand logos.',
                'icon' => 'fa-copyright',
                'counts' => [
                    'assets',
                ],
            ],


            'categories' => [
                'model' => AssetCategory::class,
                'prefix' => 'CY',
                'title' => 'Asset Categories',
                'singular' => 'Category',
                'description' =>
                    'Group related asset types into clear categories.',
                'icon' => 'fa-tags',
                'counts' => [
                    'types',
                    'assets',
                ],
            ],


            default => abort(404),
        };
    }


    /*
    |--------------------------------------------------------------------------
    | OPTIONS
    |--------------------------------------------------------------------------
    */

    private function options(
        string $module
    ): array
    {
        return match ($module) {

            'sub-departments' => [
                'department_id' =>
                    Department::orderBy('name')
                        ->pluck(
                            'name',
                            'id'
                        ),
            ],


            'sub-types' => [
                'asset_type_id' =>
                    AssetType::orderBy('name')
                        ->pluck(
                            'name',
                            'id'
                        ),
            ],


            default => [],
        };
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATION RULES
    |--------------------------------------------------------------------------
    */

    private function rules(
        string $module,
        ?int $id = null
    ): array
    {
        $common = [

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'status' => [
                'required',
                Rule::in([
                    'Active',
                    'Inactive',
                ]),
            ],

        ];


        return match ($module) {

            'departments' =>
                $common + [
                    'description' => [
                        'nullable',
                        'string',
                        'max:2000',
                    ],
                ],


            'sub-departments' =>
                $common + [

                    'department_id' => [
                        'required',
                        'exists:departments,id',
                    ],

                    'description' => [
                        'nullable',
                        'string',
                        'max:2000',
                    ],

                ],


            'types' =>
                $common + [
                    'description' => [
                        'nullable',
                        'string',
                        'max:2000',
                    ],

                ],


            'sub-types' =>
                $common + [

                    'asset_type_id' => [
                        'required',
                        'exists:asset_types,id',
                    ],
                    'is_required' => [
                        'nullable',
                        'boolean',
                    ],
                    'description' => [
                        'nullable',
                        'string',
                        'max:2000',
                    ],

                ],


            'brands' =>
                $common + [

                    'country' => [
                        'nullable',
                        'string',
                        'max:100',
                    ],

                    'support_contact' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'logo' => [
                        'nullable',
                        'image',
                        'mimes:jpg,jpeg,png,webp',
                        'max:2048',
                    ],

                ],


            'categories' =>
                $common + [
                    'description' => [
                        'nullable',
                        'string',
                        'max:2000',
                    ],
                ],


            default => [],
        };
    }
}