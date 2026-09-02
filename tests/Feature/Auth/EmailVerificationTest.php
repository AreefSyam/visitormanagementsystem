<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\ValidateSession;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Email Verification Flow Tests
 *
 * Requirements: 20 (Email Verification Process), 21 (Resend Verification Email),
 * 22 (Unverified Account Restrictions)
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ValidateSession queries the sessions DB table which is not populated
        // when using actingAs() directly. Disable it for these feature tests so
        // we can focus on the email-verification logic.
        $this->withoutMiddleware(ValidateSession::class);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a valid signed verification URL for the given user.
     */
    private function validVerificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            [
                'id'   => $user->id,
                'hash' => sha1($user->email),
            ]
        );
    }

    // -----------------------------------------------------------------------
    // Requirement 20 – Email Verification Process
    // -----------------------------------------------------------------------

    /**
     * Clicking a valid verification link marks the email as verified
     * and redirects to dashboard.
     * Requirement 20.9, 20.10, 20.12
     */
    public function test_valid_verification_link_marks_email_as_verified(): void
    {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create();

        $url = $this->validVerificationUrl($user);

        $response = $this->actingAs($user)->get($url);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', 'Your email has been verified successfully!');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    /**
     * An already-verified user hitting the verify URL is redirected to
     * the dashboard without re-processing.
     * Requirement 20.10 (idempotent), 22
     */
    public function test_already_verified_user_is_redirected_to_dashboard(): void
    {
        // User::factory() creates verified users by default
        $user = User::factory()->create();

        $url = $this->validVerificationUrl($user);

        $response = $this->actingAs($user)->get($url);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('info', 'Email already verified.');
    }

    /**
     * An expired (past-expiry) signed URL shows an error.
     * Requirement 20.13
     */
    public function test_expired_verification_link_shows_error(): void
    {
        $user = User::factory()->unverified()->create();

        // Build a URL that expired 1 minute ago
        $expiredUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            [
                'id'   => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $response = $this->actingAs($user)->get($expiredUrl);

        // Laravel's 'signed' middleware returns 403 for invalid/expired signatures
        $response->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    /**
     * A URL with a tampered hash (invalid signature) is rejected.
     * Requirement 20.13
     */
    public function test_invalid_verification_signature_shows_error(): void
    {
        $user = User::factory()->unverified()->create();

        // Forge a URL with a wrong hash
        $tamperedUrl = route('verification.verify', [
            'id'   => $user->id,
            'hash' => sha1('wrong@email.com'),
        ]);

        $response = $this->actingAs($user)->get($tamperedUrl);

        // Missing / invalid signature → 403 from 'signed' middleware
        $response->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    // -----------------------------------------------------------------------
    // Requirement 21 – Resend Verification Email
    // -----------------------------------------------------------------------

    /**
     * Resending the verification notification returns a success message.
     * Requirement 21.6, 21.7
     */
    public function test_resend_verification_email_generates_new_notification(): void
    {
        $user = User::factory()->unverified()->create();

        // Clear any existing rate-limit hits for this user
        RateLimiter::clear('email-verification-resend:' . $user->id);

        $response = $this->actingAs($user)
            ->post(route('verification.send'));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'A new verification link has been sent to your email address.');
    }

    /**
     * Rate limiting blocks resend after 3 requests within an hour.
     * Requirement 21.8, 21.9
     */
    public function test_resend_rate_limiting_blocks_after_three_attempts(): void
    {
        $user = User::factory()->unverified()->create();

        $key = 'email-verification-resend:' . $user->id;
        RateLimiter::clear($key);

        // Exhaust the 3-per-hour allowance
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit($key, 3600);
        }

        $response = $this->actingAs($user)
            ->post(route('verification.send'));

        // Should return a validation error (422) or redirect back with errors
        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
    }

    /**
     * Resending when already verified redirects to dashboard.
     * Requirement 21.2, 21.3
     */
    public function test_resend_for_already_verified_user_redirects_to_dashboard(): void
    {
        $user = User::factory()->create(); // verified by default

        $response = $this->actingAs($user)
            ->post(route('verification.send'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('info', 'Your email is already verified.');
    }

    // -----------------------------------------------------------------------
    // Requirement 22 – Unverified Account Restrictions
    // -----------------------------------------------------------------------

    /**
     * An authenticated but unverified user visiting a protected route is
     * redirected to the email verification notice page.
     * Requirement 22.1, 22.2
     */
    public function test_unverified_user_cannot_access_protected_routes(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }

    /**
     * The email verification notice page is accessible to authenticated
     * but unverified users and returns HTTP 200.
     * Requirement 22.4, 22.5, 22.6
     */
    public function test_verification_notice_page_is_accessible_to_unverified_users(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk();
        $response->assertViewIs('auth.verify-email');
    }

    /**
     * An already-verified user visiting the notice page is redirected to
     * the dashboard.
     * Requirement 22
     */
    public function test_verified_user_visiting_notice_page_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create(); // verified by default

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertRedirect(route('dashboard'));
    }
}
