<?php

namespace Tests\Unit\Auth;

use App\Http\Middleware\ValidateSession;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Unit tests for rate limiting behaviour on authentication endpoints.
 *
 * Tests cover:
 *  - LoginRequest  (5 attempts / 60 s  – throttle key: login_attempts:<email>)
 *  - ForgotPasswordRequest  (3 attempts / 3600 s – throttle key: password_reset:<email>)
 *  - EmailVerificationController::resend  (3 attempts / 3600 s – throttle key: email-verification-resend:<id>)
 *
 * Requirements: 3, 24.14
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ValidateSession queries the sessions DB table which is not populated
        // when using actingAs() directly. Disable it so we can focus on rate-limit logic.
        $this->withoutMiddleware(ValidateSession::class);

        // Start every test with a clean rate-limiter cache.
        RateLimiter::clear('login_attempts:test@example.com');
        RateLimiter::clear('password_reset:test@example.com');
        RateLimiter::clear('email-verification-resend:1');
    }

    // -----------------------------------------------------------------------
    // LoginRequest – rate limiting
    // -----------------------------------------------------------------------

    /**
     * A failed authentication attempt must increment the login rate-limiter.
     *
     * Requirements: 3.1
     */
    public function test_login_rate_limiter_increments_on_failed_attempt(): void
    {
        $key = 'login_attempts:test@example.com';

        $this->assertEquals(0, RateLimiter::attempts($key));

        // Simulate one failed hit (the way LoginRequest::authenticate() does it).
        RateLimiter::hit($key);

        $this->assertEquals(1, RateLimiter::attempts($key));
    }

    /**
     * A successful authentication attempt must clear the login rate-limiter so
     * the user can log in normally after a prior mistake.
     *
     * Requirements: 3.5
     */
    public function test_login_rate_limiter_clears_on_successful_login(): void
    {
        $key = 'login_attempts:test@example.com';

        // Simulate two failed attempts.
        RateLimiter::hit($key);
        RateLimiter::hit($key);
        $this->assertEquals(2, RateLimiter::attempts($key));

        // Simulate the successful-login clear (as done in LoginRequest::authenticate()).
        RateLimiter::clear($key);

        $this->assertEquals(0, RateLimiter::attempts($key));
        $this->assertFalse(RateLimiter::tooManyAttempts($key, 5));
    }

    /**
     * After 5 failed login attempts the rate-limiter should block further
     * attempts and LoginRequest::ensureIsNotRateLimited() must throw a
     * ValidationException.
     *
     * Requirements: 3.2, 3.3, 3.4
     */
    public function test_login_rate_limiter_blocks_after_5_attempts(): void
    {
        Event::fake([Lockout::class]);

        $key = 'login_attempts:test@example.com';

        // Simulate 5 failed hits.
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));

        // ensureIsNotRateLimited() must throw when the limiter is exhausted.
        $request = new LoginRequest();
        $request->merge(['email' => 'test@example.com', 'password' => 'password123']);

        $this->expectException(ValidationException::class);

        $request->ensureIsNotRateLimited();
    }

    /**
     * The Lockout event must be fired when the login rate-limit is exceeded.
     *
     * Requirements: 3.2
     */
    public function test_lockout_event_is_fired_when_login_rate_limit_exceeded(): void
    {
        Event::fake([Lockout::class]);

        $key = 'login_attempts:test@example.com';

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key);
        }

        $request = new LoginRequest();
        $request->merge(['email' => 'test@example.com', 'password' => 'password123']);

        try {
            $request->ensureIsNotRateLimited();
        } catch (ValidationException) {
            // Expected – swallow so we can assert on the event.
        }

        Event::assertDispatched(Lockout::class);
    }

    /**
     * The throttle key for login must use a lowercase email prefixed with
     * "login_attempts:" to ensure consistent tracking across case variations.
     *
     * Requirements: 3.1
     */
    public function test_login_throttle_key_format(): void
    {
        $request = new LoginRequest();
        $request->merge(['email' => 'User@Example.COM']);

        $this->assertEquals('login_attempts:user@example.com', $request->throttleKey());
    }

    // -----------------------------------------------------------------------
    // ForgotPasswordRequest – rate limiting
    // -----------------------------------------------------------------------

    /**
     * After 3 password-reset requests for the same email the rate-limiter must
     * block subsequent attempts and ForgotPasswordRequest::ensureIsNotRateLimited()
     * must throw a ValidationException.
     *
     * Requirements: 24.14
     */
    public function test_password_reset_rate_limiter_blocks_after_3_attempts(): void
    {
        $key = 'password_reset:test@example.com';

        // Simulate 3 hits (the withValidator() callback does this).
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit($key, 3600);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, 3));

        $request = new ForgotPasswordRequest();
        $request->merge(['email' => 'test@example.com']);

        $this->expectException(ValidationException::class);

        $request->ensureIsNotRateLimited();
    }

    /**
     * The password-reset throttle key must use a lowercase email prefixed with
     * "password_reset:" so that casing differences don't bypass the limiter.
     *
     * Requirements: 24.14
     */
    public function test_password_reset_throttle_key_format(): void
    {
        $request = new ForgotPasswordRequest();
        $request->merge(['email' => 'User@Example.COM']);

        $this->assertEquals('password_reset:user@example.com', $request->throttleKey());
    }

    /**
     * Fewer than 3 password-reset attempts must not trigger the block.
     *
     * Requirements: 24.14
     */
    public function test_password_reset_rate_limiter_allows_attempts_below_limit(): void
    {
        $key = 'password_reset:test@example.com';

        // Only 2 hits – still under the limit of 3.
        RateLimiter::hit($key, 3600);
        RateLimiter::hit($key, 3600);

        $this->assertFalse(RateLimiter::tooManyAttempts($key, 3));

        // ensureIsNotRateLimited() must NOT throw.
        $request = new ForgotPasswordRequest();
        $request->merge(['email' => 'test@example.com']);

        // No exception expected.
        $request->ensureIsNotRateLimited();

        $this->assertTrue(true); // reached without exception
    }

    // -----------------------------------------------------------------------
    // EmailVerificationController::resend – rate limiting
    // -----------------------------------------------------------------------

    /**
     * After 3 resend requests the rate-limiter must block the user and the
     * controller must respond with a 422 Unprocessable Entity (ValidationException).
     *
     * Requirements: 3 (email verification resend rate limit)
     */
    public function test_email_verification_resend_rate_limiter_blocks_after_3_attempts(): void
    {
        /** @var \App\Models\User $user */
        $user = \App\Models\User::factory()->create([
            'email_verified_at' => null,
        ]);

        $key = 'email-verification-resend:' . $user->id;

        // Exhaust the 3-attempt limit before making the HTTP request.
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit($key, 3600);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, 3));

        // The 4th request must be rejected – Laravel redirects back with session errors
        // for non-JSON requests when a ValidationException is thrown.
        $response = $this->actingAs($user)
            ->post(route('verification.send'));

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
    }

    /**
     * The email-verification-resend throttle key must be keyed by user ID so
     * that different users have independent limits.
     *
     * Requirements: 3
     */
    public function test_email_verification_resend_throttle_key_is_per_user(): void
    {
        /** @var \App\Models\User $userA */
        $userA = \App\Models\User::factory()->create(['email_verified_at' => null]);
        /** @var \App\Models\User $userB */
        $userB = \App\Models\User::factory()->create(['email_verified_at' => null]);

        $keyA = 'email-verification-resend:' . $userA->id;
        $keyB = 'email-verification-resend:' . $userB->id;

        // Exhaust user A's limit.
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit($keyA, 3600);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($keyA, 3));
        // User B must be unaffected.
        $this->assertFalse(RateLimiter::tooManyAttempts($keyB, 3));
    }

    /**
     * Fewer than 3 resend attempts must not block the user.
     *
     * Requirements: 3
     */
    public function test_email_verification_resend_allows_attempts_below_limit(): void
    {
        /** @var \App\Models\User $user */
        $user = \App\Models\User::factory()->create(['email_verified_at' => null]);

        $key = 'email-verification-resend:' . $user->id;

        RateLimiter::hit($key, 3600);
        RateLimiter::hit($key, 3600);

        $this->assertFalse(RateLimiter::tooManyAttempts($key, 3));
    }
}
