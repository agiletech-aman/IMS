<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Services\NotificationService;
use App\Support\UniqueCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function index(Request $request): View
    {
        $query = Vendor::query();

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($type = $request->query('type')) {
            $query->where('vendor_type', $type);
        }

        return view('vendors.index', [
            'vendors' => $query->latest()->paginate(10)->withQueryString(),
            'stats' => [
                'total' => Vendor::count(),
                'active_contracts' => Vendor::where('amc_status', 'Active')->count(),
                'renewals_due' => Vendor::where('amc_status', 'Renewal Due')->count(),
                'preferred' => Vendor::where('preferred', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['code'] = UniqueCodeGenerator::generate('vendors', 'VN', 'vendors', 'code');
        $data['preferred'] = $request->boolean('preferred');
        $vendor = Vendor::create($data);
        $this->notifyVendor($vendor, 'created');

        return back()->with('success', 'Vendor / OEM created successfully.');
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['preferred'] = $request->boolean('preferred');
        $vendor->update($data);
        $this->notifyVendor($vendor->fresh(), 'updated');

        return back()->with('success', 'Vendor / OEM updated successfully.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $name = $vendor->name;
        $code = $vendor->code;
        $vendor->delete();
        $this->notifications->send(
            'vendor_changed',
            'Vendor / OEM deleted',
            "{$name} ({$code}) was removed from the vendor directory.",
            'warning',
            'Vendors',
        );

        return back()->with('success', 'Vendor / OEM deleted successfully.');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'vendor_type' => ['required', Rule::in(['Vendor', 'OEM', 'Vendor & OEM'])],
            'category' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'amc_status' => ['required', Rule::in(['Active', 'Renewal Due', 'Expired', 'Not Applicable'])],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'preferred' => ['nullable', 'boolean'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function notifyVendor(Vendor $vendor, string $action): void
    {
        $this->notifications->send(
            'vendor_changed',
            'Vendor / OEM '.ucfirst($action),
            "{$vendor->name} ({$vendor->code}) was {$action}. Contract status: {$vendor->amc_status}.",
            'info',
            'Vendors',
            ['vendor_id' => $vendor->id],
        );
    }
}
