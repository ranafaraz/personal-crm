<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $default = auth()->user()->isSuperAdmin()
                ? route('admin.dashboard')
                : route('dashboard');
            return redirect()->intended($default);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'email'             => 'required|email|max:255|unique:users,email',
            'password'          => 'required|string|min:8|confirmed',
            'organization_name' => 'nullable|string|max:255',
        ]);

        // Auto-create a tenant for every self-registration
        $orgName = $data['organization_name'] ?? $data['name'] . "'s Workspace";
        $slug    = Str::slug($orgName);
        $base    = $slug;
        $i = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        $tenant = Tenant::create([
            'name'          => $orgName,
            'slug'          => $slug,
            'email'         => $data['email'],
            'plan'          => 'free',
            'status'        => 'trial',
            'max_users'     => 3,
            'trial_ends_at' => now()->addDays(14),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'role'      => 'admin',
            'is_active' => true,
        ]);

        UserSetting::create([
            'user_id'                => $user->id,
            'timezone'               => 'UTC',
            'date_format'            => 'Y-m-d',
            'default_follow_up_days' => 3,
            'notify_on_reply'        => true,
            'notify_on_bounce'       => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
