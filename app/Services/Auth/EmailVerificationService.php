<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class EmailVerificationService
{
    /**
     * Send verification email to the user.
     *
     * Generates a signed URL and sends verification notification.
     * Logs the verification email sent event for audit purposes.
     *
     * @param User $user
     * @return void
     */
    public function sendVerificationEmail(User $user): void
    {
        // Generate signed URL and send notification
        $user->sendEmailVerificationNotification();

        // Audit logging for verification email sent
        Log::info('Email verification sent', [
            'email' => $user->email,
            'ip_address' => request()->ip(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Mark user's email as verified.
     *
     * Sets the email_verified_at timestamp to current time.
     * Logs the successful verification for audit purposes.
     *
     * @param User $user
     * @return void
     */
    public function verify(User $user): void
    {
        // Mark email as verified
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            // Audit logging for successful email verification (Requirement 26.4)
            Log::info('Email verified successfully', [
                'email' => $user->email,
                'timestamp' => now()->toDateTimeString(),
            ]);
        }
    }

    /**
     * Generate a 24-hour signed URL for email verification.
     *
     * Creates a temporary signed URL that expires in 24 hours.
     * The URL includes the user ID and hash for security.
     *
     * @param User $user
     * @return string
     */
    public function generateVerificationToken(User $user): string
    {
        // Generate signed URL with 24 hour expiration (Requirement 20.3)
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );
    }
}
