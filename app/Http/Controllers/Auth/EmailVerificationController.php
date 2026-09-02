<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function __construct(
        protected EmailVerificationService $emailVerificationService
    ) {}

    /**
     * Display the email verification notice.
     */
    public function notice(): View|RedirectResponse
    {
        // If already verified, redirect to dashboard
        if (Auth::user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email');
    }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verify(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Check if already verified
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard')->with('info', 'Email already verified.');
        }

        // Validate the signature and ID
        if (!hash_equals((string) $request->route('id'), (string) $user->getKey())) {
            Log::warning('Email verification failed: Invalid user ID', [
                'user_id' => $user->id,
                'provided_id' => $request->route('id'),
                'ip' => $request->ip(),
            ]);

            return redirect()->route('verification.notice')
                ->with('error', 'Invalid verification link.');
        }

        if (!hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            Log::warning('Email verification failed: Invalid hash', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('verification.notice')
                ->with('error', 'Invalid verification link.');
        }

        // Verify the email
        $this->emailVerificationService->verify($user);

        // Fire the Verified event
        event(new Verified($user));

        // Log verification event
        Log::info('Email verified successfully', [
            'email' => $user->email,
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'timestamp' => now(),
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Your email has been verified successfully!');
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Check if already verified
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard')
                ->with('info', 'Your email is already verified.');
        }

        // Apply rate limiting: 3 attempts per 60 minutes
        $key = 'email-verification-resend:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = ceil($seconds / 60);

            throw ValidationException::withMessages([
                'email' => "Too many verification emails sent. Please try again in {$minutes} minutes.",
            ]);
        }

        RateLimiter::hit($key, 3600); // 60 minutes

        // Send verification email
        $this->emailVerificationService->sendVerificationEmail($user);

        // Log resend event
        Log::info('Verification email resent', [
            'email' => $user->email,
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'timestamp' => now(),
        ]);

        return back()->with('success', 'A new verification link has been sent to your email address.');
    }
}
