<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Display the login form.
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        try {
            // Validate credentials and authenticate
            $request->authenticate();

            // Regenerate session ID to prevent session fixation attacks
            $request->session()->regenerate();

            // Handle "Remember Me" functionality
            if ($request->boolean('remember')) {
                Auth::viaRemember();
            }

            // Log successful login attempt
            Log::info('User logged in successfully', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'timestamp' => now(),
                'user_agent' => $request->userAgent(),
            ]);

            // Flash success message
            session()->flash('success', 'Welcome back, ' . Auth::user()->name . '!');

            // Redirect to intended URL or dashboard
            return redirect()->intended(route('dashboard'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log failed login attempt
            Log::warning('Failed login attempt', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'timestamp' => now(),
                'reason' => 'invalid_credentials',
            ]);

            throw $e;
        }
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Log logout event before destroying session
        if ($user) {
            Log::info('User logged out', [
                'email' => $user->email,
                'ip' => $request->ip(),
                'timestamp' => now(),
            ]);

            // Delete remember tokens
            $user->tokens()->delete();
        }

        // Logout the user
        Auth::logout();

        // Invalidate the session
        $request->session()->invalidate();

        // Regenerate CSRF token
        $request->session()->regenerateToken();

        // Flash success message
        session()->flash('success', 'You have been logged out successfully.');

        return redirect()->route('login');
    }
}
