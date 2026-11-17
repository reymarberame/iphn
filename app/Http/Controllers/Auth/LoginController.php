<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login', [
            'demoAccounts' => config('iphn.demo_accounts'),
            'demoPassword' => config('iphn.demo_password'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('success', 'Welcome back. Your session is secure.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'You have been signed out.');
    }
}
