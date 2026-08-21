<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaticAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $authenticatedUser = $request->session()->get('static_auth_user');
        if (! $authenticatedUser) {
            return redirect()->route('login');
        }

        if (filled($authenticatedUser['admin_id'] ?? null)) {
            $adminIsActive = Admin::whereKey($authenticatedUser['admin_id'])
                ->where('status', 'Active')
                ->exists();

            if (! $adminIsActive) {
                $request->session()->forget('static_auth_user');

                return redirect()->route('login')->withErrors([
                    'email' => 'This administrator account is inactive or no longer available.',
                ]);
            }
        } elseif (filled($authenticatedUser['user_id'] ?? null)) {
            $account = User::whereKey($authenticatedUser['user_id'])
                ->where('login_enabled', true)
                ->where('status', 'Active')
                ->first();

            if (! $account) {
                $request->session()->forget('static_auth_user');

                return redirect()->route('login')->withErrors([
                    'email' => 'This dashboard access account is inactive or no longer available.',
                ]);
            }

            $request->session()->put('static_auth_user', array_merge($authenticatedUser, [
                'name' => $account->name,
                'email' => $account->email,
                'role' => $account->role,
                'centre' => $account->centre,
                'image_path' => $account->image_path,
            ]));
        }

        return $next($request);
    }
}
