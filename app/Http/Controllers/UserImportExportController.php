<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Support\UniqueCodeGenerator;
use App\Support\UserCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserImportExportController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function importSampleCsv(Request $request): BinaryFileResponse
    {
        $internalTeam = $request->filled('role');
        $filename = $internalTeam ? 'internal_team_import_sample.xlsx' : 'users_import_sample.xlsx';
        $tmpPath = storage_path('app/' . $filename);

        if ($internalTeam) {
            UserCsv::exportStyledInternalTeamSample($tmpPath);
        } else {
            UserCsv::exportStyledImportSample($tmpPath);
        }
        $this->audit->record('DOWNLOAD', 'Users', 'User import sample was downloaded.');

        return response()->download($tmpPath)->deleteFileAfterSend(true);
    }

    public function exportCsv(): BinaryFileResponse
    {
        $filename = 'users_export_' . date('Ymd_His') . '.xlsx';
        $tmpPath = storage_path('app/' . $filename);

        UserCsv::exportStyledExcel($tmpPath);
        $this->audit->record(
            'EXPORT',
            'Users',
            'User directory was exported to Excel.',
            metadata: ['record_count' => Faculty::count(), 'format' => 'xlsx'],
        );

        return response()->download($tmpPath)->deleteFileAfterSend(true);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'csv' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ], [
            'csv.required' => 'Please select an Excel or CSV file to import.',
            'csv.file' => 'The selected upload is not a valid file.',
            'csv.mimes' => 'Only XLSX, XLS, CSV, and TXT files are allowed.',
            'csv.max' => 'The import file must not be larger than 5 MB.',
        ]);

        if ($validator->fails()) {
            $this->audit->record(
                'IMPORT',
                'Users',
                'User import was rejected during file validation.',
                result: 'Failed',
                metadata: ['errors' => $validator->errors()->all()],
            );

            return back()
                ->withErrors($validator, 'userImport')
                ->with('openUserImportModal', true);
        }

        $file = $request->file('csv');
        $internalTeam = $request->filled('role');
        $parsed = UserCsv::parseFile($file->getRealPath(), $internalTeam);
        $rows = $parsed['rows'] ?? [];
        $parseErrors = $parsed['errors'] ?? [];

        $errors = [];
        $importCount = 0;

        if (! empty($parseErrors)) {
            $this->audit->record(
                'IMPORT',
                'Users',
                'User import file could not be parsed.',
                result: 'Failed',
                metadata: ['errors' => $parseErrors],
            );

            return back()
                ->with('userImportErrors', [['row' => null, 'user' => null, 'messages' => $parseErrors]])
                ->with('userImportResult', ['errors' => count($parseErrors), 'inserted' => 0])
                ->with('error', $parseErrors[0] ?? 'The import file could not be read.')
                ->with('openUserImportModal', true);
        }

        if ($rows === []) {
            $this->audit->record(
                'IMPORT',
                'Users',
                'User import file contained no data rows.',
                result: 'Failed',
            );

            return back()
                ->with('userImportErrors', [[
                    'row' => null,
                    'user' => null,
                    'messages' => ['The import file contains headings but no user rows.'],
                ]])
                ->with('userImportResult', ['errors' => 1, 'inserted' => 0])
                ->with('error', 'The import file contains headings but no user rows.')
                ->with('openUserImportModal', true);
        }

        foreach ($rows as $rowNumber => $row) {
            $rowErrors = $this->validateRow($row, $internalTeam);

            if ($rowErrors !== []) {
                $errors[] = [
                    'row' => $rowNumber,
                    'user' => trim((string) ($row['name'] ?? '')) ?: 'Unnamed user',
                    'messages' => $rowErrors,
                ];

                continue;
            }

            $email = trim((string) ($row['email'] ?? ''));
            if (($internalTeam ? User::where('email', $email) : Faculty::where('email', $email))->exists()) {
                $errors[] = [
                    'row' => $rowNumber,
                    'user' => trim((string) ($row['name'] ?? '')) ?: 'Unnamed user',
                    'messages' => ["Email '{$email}' already exists."],
                ];

                continue;
            }

            $status = trim((string) ($row['status'] ?? ''));
            $status = in_array($status, ['Active', 'Inactive'], true) ? $status : 'Active';

            if ($internalTeam) {
                $role = trim((string) ($row['role'] ?? '')) ?: trim((string) $request->input('role'));

                try {
                    User::create([
                        'unique_id' => UniqueCodeGenerator::generate('people', 'ID', ['users', 'faculties'], 'unique_id'),
                        'name' => trim((string) ($row['name'] ?? '')),
                        'email' => $email,
                        'contact' => trim((string) ($row['contact'] ?? '')) ?: null,
                        'address' => trim((string) ($row['address'] ?? '')) ?: null,
                        'status' => $status,
                        'role' => $role,
                        'login_enabled' => true,
                        'password' => Str::password(32),
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                    $errors[] = [
                        'row' => $rowNumber,
                        'user' => trim((string) ($row['name'] ?? '')) ?: 'Unnamed user',
                        'messages' => ['Could not save this user. Check for a duplicate email or invalid value.'],
                    ];

                    continue;
                }

                $importCount++;
                continue;
            }
            $departmentValue = trim((string) ($row['department'] ?? ''));
            $department = $this->resolveDepartment($departmentValue);

            try {
                $user = Faculty::create([
                    'unique_id' => UniqueCodeGenerator::generate('faculties', 'USR', 'faculties', 'unique_id'),
                    'name' => trim((string) ($row['name'] ?? '')),
                    'email' => $email,
                    'contact' => trim((string) ($row['contact'] ?? '')) ?: null,
                    'address' => trim((string) ($row['address'] ?? '')) ?: null,
                    'department_id' => $department?->id,
                    'fb_type' => trim((string) ($row['fb_type'] ?? '')) ?: null,
                    'room_number' => trim((string) ($row['room_number'] ?? '')) ?: null,
                    'remark' => trim((string) ($row['remark'] ?? '')) ?: null,
                    'status' => $status,
                ]);
            } catch (\Throwable $e) {
                report($e);
                $errors[] = [
                    'row' => $rowNumber,
                    'user' => trim((string) ($row['name'] ?? '')) ?: 'Unnamed user',
                    'messages' => ['Could not save this user. Check for a duplicate email or invalid value.'],
                ];

                continue;
            }

            $importCount++;

            $this->notifications->send(
                'user_created',
                'New user created',
                "{$user->name} ({$user->unique_id}) was added to the user directory.",
                'info',
                'Users',
                ['user_id' => $user->id],
            );
        }

        $response = back()->with('userImportErrors', $errors)
            ->with('userImportResult', [
                'errors' => count($errors),
                'inserted' => $importCount,
            ])
            ->with('openUserImportModal', $errors !== []);
        $this->audit->record(
            'IMPORT',
            'Users',
            "User import completed with {$importCount} inserted and " . count($errors) . ' failed rows.',
            result: $errors === [] ? 'Success' : 'Failed',
            metadata: ['inserted' => $importCount, 'failed' => count($errors)],
        );

        if ($errors !== []) {
            return $response->with(
                'warning',
                count($errors) . ' row(s) could not be imported. Please check the row-wise errors.'
            );
        }

        return $response->with(
            'success',
            "User import completed: {$importCount} user(s) imported successfully."
        );
    }

    private function validateRow(array $row, bool $internalTeam = false): array
    {
        $errors = [];

        if (trim((string) ($row['name'] ?? '')) === '') {
            $errors[] = 'name is required.';
        }

        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '') {
            $errors[] = 'email is required.';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "email is invalid: {$email}";
        }

        $status = trim((string) ($row['status'] ?? ''));
        if ($status !== '' && ! in_array($status, ['Active', 'Inactive'], true)) {
            $errors[] = "Invalid status: {$status}";
        }

        if ($internalTeam) {
            $role = trim((string) ($row['role'] ?? '')) ?: trim((string) request()->input('role'));
            if (! in_array($role, User::ROLES, true)) {
                $errors[] = 'A valid Internal Team role is required.';
            }

            return $errors;
        }

        $department = trim((string) ($row['department'] ?? ''));
        if ($department === '') {
            $errors[] = 'department is required.';
        } elseif (! $this->resolveDepartment($department)) {
            $errors[] = "Department not found or inactive: {$department}";
        }

        $fbType = trim((string) ($row['fb_type'] ?? ''));
        if ($fbType === '') {
            $errors[] = 'FB type is required.';
        } elseif (mb_strlen($fbType) > 30) {
            $errors[] = 'FB type must not be longer than 30 characters.';
        }

        return $errors;
    }

    /**
     * Resolve a department by name or code, scoped to Active records only, with a
     * deterministic order so validation and creation always resolve the same row.
     */
    private function resolveDepartment(string $value): ?Department
    {
        return Department::query()
            ->where('status', 'Active')
            ->where(function ($query) use ($value): void {
                $query->where('name', $value)->orWhere('code', $value);
            })
            ->orderBy('id')
            ->first();
    }
}
