<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(
        protected EmailVerificationService $emailVerificationService
    ) {}

    /**
     * Display the registration form.
     */
    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    /**
     * Handle a registration request.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        // Create the user with validated data
        $user = User::create([
            'name' => $request->name,
            'email' => strtolower($request->email),
            'password' => Hash::make($request->password),
            'email_verified_at' => null,
        ]);

        // Send email verification notification
        $this->emailVerificationService->sendVerificationEmail($user);

        // Log registration event
        Log::info('New user registered', [
            'email' => $user->email,
            'ip' => $request->ip(),
            'timestamp' => now(),
        ]);

        // Flash success message
        session()->flash('success', 'Registration successful! Please check your email to verify your account.');

        return redirect()->route('verification.notice');
    }
}
