<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Models\AssetSubtype;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Department;
use App\Models\SubDepartment;
use App\Support\SubtypeCsv;
use App\Support\UniqueCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        if ($module === 'sub-types') {
            return $this->exportSubtypesWorkbook($request);
        }

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

        if ($module === 'sub-types') {
            return $this->importSubtypesWorkbook($request);
        }

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
    | SUB-TYPES WORKBOOK (one sheet per Asset Type, like the Assets bulk import)
    |--------------------------------------------------------------------------
    */

    public function subtypeImportSample(Request $request): BinaryFileResponse
    {
        $typeIds = $this->resolveSubtypeTypeIds($request);

        $filename = 'sub-types_import_sample.xlsx';
        $tmpPath = storage_path('app/'.$filename);

        SubtypeCsv::exportWorkbook($typeIds, $tmpPath, withData: false);

        return response()->download($tmpPath)->deleteFileAfterSend(true);
    }

    private function exportSubtypesWorkbook(Request $request): BinaryFileResponse
    {
        $typeIds = $this->resolveSubtypeTypeIds($request);

        $filename = 'sub-types_export_'.now()->format('Y-m-d-His').'.xlsx';
        $tmpPath = storage_path('app/'.$filename);

        SubtypeCsv::exportWorkbook($typeIds, $tmpPath, withData: true);

        return response()->download($tmpPath)->deleteFileAfterSend(true);
    }

    /**
     * The Asset Type ids selected for a Sub-Types export/sample download.
     * Falls back to every active type when none are explicitly checked.
     */
    private function resolveSubtypeTypeIds(Request $request): array
    {
        $requested = collect($request->input('types', []))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        return AssetType::where('status', 'Active')
            ->when($requested->isNotEmpty(), fn ($query) => $query->whereIn('id', $requested))
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    private function importSubtypesWorkbook(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $parsed = SubtypeCsv::parseWorkbook($request->file('file')->getRealPath());
        $sheets = $parsed['sheets'] ?? [];
        $fileErrors = $parsed['fileErrors'] ?? [];

        if ($sheets === []) {
            return back()->with(
                'error',
                $fileErrors[0] ?? 'The import file could not be read.'
            );
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        DB::beginTransaction();

        try {

            foreach ($sheets as $sheet) {
                $type = $sheet['type'];

                foreach ($sheet['rows'] as $row) {
                    $name = trim((string) ($row['name'] ?? ''));

                    if ($name === '') {
                        $skipped++;

                        continue;
                    }

                    $resolved = SubtypeCsv::resolveRow($type, $row);

                    if ($resolved['errors'] !== []) {
                        $skipped++;

                        continue;
                    }

                    $data = $resolved['resolved'];
                    $data['name'] = $name;

                    $providedCode = trim((string) ($row['code'] ?? ''));

                    $existing = $providedCode !== ''
                        ? AssetSubtype::where('code', $providedCode)->first()
                        : null;

                    if ($existing) {

                        $existing->update($data);

                        $updated++;

                        continue;

                    }

                    $code = $providedCode;

                    if ($code === '') {
                        $code = UniqueCodeGenerator::generate(
                            'sub-types',
                            'SY',
                            (new AssetSubtype())->getTable(),
                            'code'
                        );
                    }

                    AssetSubtype::create($data + [
                        'code' => $code,
                        'is_required' => true,
                    ]);

                    $inserted++;
                }
            }

            DB::commit();

        } catch (\Throwable $exception) {

            DB::rollBack();

            report($exception);

            return back()->with(
                'error',
                'Import failed. No changes were saved.'
            );

        }

        $message = "{$inserted} created, {$updated} updated";

        if ($skipped > 0) {
            $message .= ", {$skipped} skipped";
        }

        foreach ($fileErrors as $fileError) {
            $message .= '. '.$fileError;
        }

        return back()->with('success', $message.'.');
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


        /*
        |--------------------------------------------------------------------------
        | Subtype Parameters
        |--------------------------------------------------------------------------
        */

        if ($module === 'types') {
            $data['parameters'] = $this->cleanParameters($data['parameters'] ?? []);
        }

        if ($module === 'sub-types') {
            $data['parameter_values'] = $this->filteredParameterValues($request->input('parameter_values', []), $data['asset_type_id']);
        }


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


        /*
        |--------------------------------------------------------------------------
        | Subtype Parameters
        |--------------------------------------------------------------------------
        */

        if ($module === 'types') {
            $data['parameters'] = $this->cleanParameters($data['parameters'] ?? []);
        }

        if ($module === 'sub-types') {
            $data['parameter_values'] = $this->filteredParameterValues($request->input('parameter_values', []), $data['asset_type_id']);
        }


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

            'parameters' =>
                $this->cleanParameters(
                    $this->splitDelimitedList(
                        $data['parameters']
                        ?? null
                    )
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


        $brandName = trim(
            (string) (
                $data['brand']
                ?? ''
            )
        );


        if ($brandName === '') {
            return null;
        }


        $brand = Brand::query()
            ->whereRaw(
                'LOWER(name) = ?',
                [strtolower($brandName)]
            )
            ->first();


        if (!$brand) {
            return null;
        }


        $rawParameterValues = [];

        foreach ($this->allSubtypeParameterNames() as $parameter) {

            $value = trim(
                (string) (
                    $data[strtolower(trim($parameter))]
                    ?? ''
                )
            );

            if ($value !== '') {
                $rawParameterValues[$parameter] = $value;
            }

        }


        return $common + [

            'asset_type_id' =>
                $assetType->id,
            'brand_id' =>
                $brand->id,
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
            'parameter_values' =>
                $this->filteredParameterValues(
                    $rawParameterValues,
                    $assetType->id
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
                'parameters',
                'assets',
            ],

            'sub-types' => [
                'name',
                'code',
                'asset_type',
                'brand',
                'status',
                'description',
                ...$this->allSubtypeParameterNames(),
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
                implode('|', $record->parameters ?? []),
                $record->assets_count,
            ],

            'sub-types' => [
                $record->name,
                $record->code,
                $record->assetType?->name,
                $record->brand?->name,
                $record->status,
                $record->description,
                ...collect($this->allSubtypeParameterNames())
                    ->map(fn (string $parameter) => $record->parameter_values[$parameter] ?? '')
                    ->all(),
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
    | SUBTYPE PARAMETERS
    |--------------------------------------------------------------------------
    */

    private function cleanParameters(array $parameters): array
    {
        return collect($parameters)
            ->map(fn ($parameter) => trim((string) $parameter))
            ->filter(fn ($parameter) => $parameter !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function filteredParameterValues(array $values, ?int $assetTypeId): array
    {
        $allowed = AssetType::find($assetTypeId)?->parameters ?? [];

        return collect($values)
            ->only($allowed)
            ->all();
    }

    /**
     * "RAM|Processor|Storage" -> ['RAM', 'Processor', 'Storage'] — the CSV
     * format for a Type's parameter key list (used by export/import).
     */
    private function splitDelimitedList(?string $value): array
    {
        if (trim((string) $value) === '') {
            return [];
        }

        return array_map('trim', explode('|', $value));
    }

    /**
     * The deduplicated set of Subtype Parameter names configured across every
     * Asset Type, in first-seen order — the CSV/export column list for
     * sub-types is built from this so it always reflects current config.
     */
    private function allSubtypeParameterNames(): array
    {
        return AssetType::query()
            ->pluck('parameters')
            ->filter()
            ->flatMap(fn (array $parameters) => $parameters)
            ->map(fn ($parameter) => trim((string) $parameter))
            ->filter(fn ($parameter) => $parameter !== '')
            ->unique()
            ->values()
            ->all();
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
                    'brand',
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
                'type_parameters' =>
                    AssetType::pluck(
                        'parameters',
                        'id'
                    ),
                'brand_id' =>
                    Brand::orderBy('name')
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

                    'parameters' => [
                        'nullable',
                        'array',
                    ],

                    'parameters.*' => [
                        'nullable',
                        'string',
                        'max:150',
                    ],

                ],


            'sub-types' =>
                $common + [

                    'asset_type_id' => [
                        'required',
                        'exists:asset_types,id',
                    ],
                    'brand_id' => [
                        'required',
                        'exists:brands,id',
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
                    'parameter_values' => [
                        'nullable',
                        'array',
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