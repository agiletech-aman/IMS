<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('admins', 'public');
        }
        unset($data['image']);

        Admin::create($data);

        return back()->with('success', 'Administrator account created successfully.')
            ->with('activeSettingsTab', 'administrators');
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $data = $request->validate($this->rules($admin));
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($request->hasFile('image')) {
            if ($admin->image_path) {
                Storage::disk('public')->delete($admin->image_path);
            }
            $data['image_path'] = $request->file('image')->store('admins', 'public');
        }
        unset($data['image']);

        if (
            $admin->id === $request->session()->get('static_auth_user.admin_id')
            && ($data['status'] ?? $admin->status) !== 'Active'
        ) {
            return back()->with('error', 'You cannot deactivate your currently signed-in administrator account.')
                ->with('activeSettingsTab', 'administrators');
        }

        $admin->update($data);

        if ($admin->id === $request->session()->get('static_auth_user.admin_id')) {
            $request->session()->put('static_auth_user.name', $admin->name);
            $request->session()->put('static_auth_user.email', $admin->email);
            $request->session()->put('static_auth_user.initials', $this->initials($admin->name));
            $request->session()->put('static_auth_user.image_path', $admin->image_path);
        }

        return back()->with('success', 'Administrator account updated successfully.')
            ->with('activeSettingsTab', 'administrators');
    }

    public function destroy(Request $request, Admin $admin): RedirectResponse
    {
        if ($admin->id === $request->session()->get('static_auth_user.admin_id')) {
            return back()->with('error', 'You cannot delete your currently signed-in administrator account.')
                ->with('activeSettingsTab', 'administrators');
        }

        if (Admin::where('status', 'Active')->count() <= 1 && $admin->status === 'Active') {
            return back()->with('error', 'The last active administrator cannot be deleted.')
                ->with('activeSettingsTab', 'administrators');
        }

        $imagePath = $admin->image_path;
        $admin->delete();
        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        return back()->with('success', 'Administrator account deleted successfully.')
            ->with('activeSettingsTab', 'administrators');
    }

    private function rules(?Admin $admin = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin?->id)],
            'password' => [$admin ? 'nullable' : 'required', 'nullable', 'string', 'min:8', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }

    private function initials(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->join('');
    }
}
