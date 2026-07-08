<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request, AuthService $authService): RedirectResponse
    {
        $user = $authService->attempt(
            $request->input('company_code'),
            $request->input('email'),
            $request->input('password'),
        );

        if ($user === null) {
            return back()
                ->withErrors(['login' => 'ログイン情報が正しくありません'])
                ->withInput($request->only('company_code', 'email'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($authService->homeRouteFor($user));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
