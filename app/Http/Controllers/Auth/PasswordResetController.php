<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /**
     * Display the forgot password form.
     */
    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a password reset link to the user's email.
     */
    public function sendResetLinkEmail(ForgotPasswordRequest $request): RedirectResponse
    {
        // Attempt to send the password reset link
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Log the reset request (but never log if email exists or not)
        Log::info('Password reset link requested', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'timestamp' => now(),
        ]);

        // Always show success message (security best practice)
        // Don't reveal whether the email exists in the system
        return back()->with('success', 'If that email address exists in our system, you will receive a password reset link shortly.');
    }

    /**
     * Display the password reset form.
     */
    public function showResetForm(Request $request, string $token): View|RedirectResponse
    {
        // Check if the token exists in the database
        $resetRecord = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord) {
            return redirect()->route('password.request')
                ->with('error', 'This password reset link is invalid or has expired. Please request a new one.');
        }

        // Check if token has expired (60 minutes)
        $expiresAt = \Carbon\Carbon::parse($resetRecord->created_at)->addMinutes(config('auth.passwords.users.expire', 60));

        if (now()->greaterThan($expiresAt)) {
            return redirect()->route('password.request')
                ->with('error', 'This password reset link has expired. Please request a new one.');
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        // Attempt to reset the user's password
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use ($request) {
                // Update the user's password — the 'hashed' cast on the model
                // handles bcrypt hashing using the configured rounds (BCRYPT_ROUNDS).
                $user->forceFill(['password' => $password])->save();

                // Clear the remember token for security
                $user->forceFill(['remember_token' => null])->save();

                // Log password change event
                Log::info('Password reset successfully', [
                    'email' => $user->email,
                    'ip' => $request->ip(),
                    'timestamp' => now(),
                ]);
            }
        );

        // Check the status and redirect accordingly
        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('success', 'Your password has been reset successfully. Please log in with your new password.');
        }

        // Log failed reset attempt
        Log::warning('Password reset failed', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'status' => $status,
            'timestamp' => now(),
        ]);

        return back()
            ->withErrors(['email' => __($status)])
            ->withInput($request->only('email'));
    }
}
