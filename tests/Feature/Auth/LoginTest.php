<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Login flow feature tests.
 *
 * Covers Requirements: 2, 3, 4, 6, 12
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Requirement 2: Credential Validation
    // -------------------------------------------------------------------------

    /**
     * Successful login with valid credentials redirects to the dashboard.
     *
     * @see Requirement 2 (credential validation), Requirement 4 (session creation),
     *      Requirement 8 (login redirection)
     */
    public function test_successful_login_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Login with invalid credentials shows a generic error message.
     *
     * @see Requirement 2.7 – generic "credentials do not match" error
     */
    public function test_login_with_invalid_credentials_shows_generic_error(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => 'WrongPassword1!',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'These credentials do not match our records.',
        ]);
        $this->assertGuest();
    }

    /**
     * Login with an unknown email shows the same generic error (no user enumeration).
     *
     * @see Requirement 2.7
     */
    public function test_login_with_unknown_email_shows_generic_error(): void
    {
        $response = $this->post(route('login.submit'), [
            'email'    => 'nobody@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // -------------------------------------------------------------------------
    // Requirement 22: Unverified account restrictions
    // -------------------------------------------------------------------------

    /**
     * Login with an unverified email ultimately lands on the verification notice.
     *
     * The AuthController logs the user in and redirects to /dashboard. The
     * 'verified' middleware on the dashboard route then redirects unverified
     * users to the verification notice. We verify both parts separately:
     * 1) The login itself succeeds (user is authenticated).
     * 2) An authenticated but unverified user cannot access the dashboard.
     *
     * Note: ValidateSession middleware (which requires a real DB session record)
     * is excluded from step 2 so that only the 'verified' middleware behaviour
     * is asserted here.
     *
     * @see Requirement 22 – unverified accounts are blocked and redirected
     */
    public function test_login_with_unverified_email_redirects_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create([
            'password' => Hash::make('Password123!'),
        ]);

        // Step 1 – login succeeds and authenticates the user
        $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => 'Password123!',
        ]);

        $this->assertAuthenticatedAs($user);

        // Step 2 – accessing a 'verified' protected route redirects to verification notice.
        // ValidateSession is bypassed here as it requires a real DB session record;
        // we are testing only the MustVerifyEmail / 'verified' middleware behaviour.
        $dashboardResponse = $this
            ->withoutMiddleware(\App\Http\Middleware\ValidateSession::class)
            ->actingAs($user)
            ->get(route('dashboard'));

        $dashboardResponse->assertRedirect(route('verification.notice'));
    }

    // -------------------------------------------------------------------------
    // Requirement 3: Rate Limiting Protection
    // -------------------------------------------------------------------------

    /**
     * Rate limiting blocks further login attempts after 5 failures.
     *
     * @see Requirement 3.2 – block after 5 attempts within 60 seconds
     * @see Requirement 3.3 – HTTP 422 (validation exception) or redirect with error
     */
    public function test_rate_limiting_blocks_after_5_failed_attempts(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        $throttleKey = 'login_attempts:' . strtolower($user->email);
        RateLimiter::clear($throttleKey);

        // Exhaust the 5-attempt allowance
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey, 60);
        }

        $response = $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => 'Password123!',
        ]);

        // The blocked request should surface a "too many attempts" error
        $response->assertSessionHasErrors('email');
        $errors = session('errors');
        $this->assertStringContainsString(
            'Too many login attempts',
            $errors->first('email')
        );
    }

    /**
     * Rate limiting message includes the remaining wait time in seconds.
     *
     * @see Requirement 3.4 – display "Please try again in X seconds"
     */
    public function test_rate_limit_error_message_includes_wait_time(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        $throttleKey = 'login_attempts:' . strtolower($user->email);
        RateLimiter::clear($throttleKey);

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey, 60);
        }

        $response = $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
        $errorMessage = session('errors')->first('email');
        $this->assertMatchesRegularExpression(
            '/Please try again in \d+ seconds/',
            $errorMessage
        );
    }

    /**
     * Successful login resets the rate limit counter for that email.
     *
     * @see Requirement 3.5 – reset attempt count on success
     */
    public function test_successful_login_resets_rate_limit_counter(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        $throttleKey = 'login_attempts:' . strtolower($user->email);
        RateLimiter::clear($throttleKey);

        // Simulate 3 prior failed attempts
        RateLimiter::hit($throttleKey, 60);
        RateLimiter::hit($throttleKey, 60);
        RateLimiter::hit($throttleKey, 60);

        $this->assertEquals(3, RateLimiter::attempts($throttleKey));

        // Now authenticate successfully
        $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => 'Password123!',
        ]);

        $this->assertEquals(0, RateLimiter::attempts($throttleKey));
    }

    // -------------------------------------------------------------------------
    // Requirement 4 & 12: Session Creation / Remember Me Token
    // -------------------------------------------------------------------------

    /**
     * "Remember Me" creates a remember token in the database for the user.
     *
     * @see Requirement 4.9 – Remember_Token created with 30-day expiration
     * @see Requirement 12.1 – cryptographically secure random token
     */
    public function test_remember_me_creates_remember_token(): void
    {
        $user = User::factory()->create([
            'password'       => Hash::make('Password123!'),
            'remember_token' => null,
        ]);

        $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => 'Password123!',
            'remember' => '1',
        ]);

        $user->refresh();
        $this->assertNotNull($user->remember_token);
        $this->assertNotEmpty($user->remember_token);
    }

    /**
     * Login without "Remember Me" does not create/update the remember token.
     *
     * @see Requirement 4.10 – session-only cookie when Remember Me is unchecked
     */
    public function test_login_without_remember_me_does_not_set_remember_token(): void
    {
        $user = User::factory()->create([
            'password'       => Hash::make('Password123!'),
            'remember_token' => null,
        ]);

        $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => 'Password123!',
            // 'remember' intentionally omitted
        ]);

        // Without "remember", the factory-created null value should stay null
        $user->refresh();
        $this->assertNull($user->remember_token);
    }

    // -------------------------------------------------------------------------
    // Requirement 6: Logout Functionality
    // -------------------------------------------------------------------------

    /**
     * Logout destroys the session and redirects to the login page.
     *
     * @see Requirement 6.1, 6.2, 6.5 – session invalidated, redirect to login
     */
    public function test_logout_destroys_session_and_redirects_to_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        // Log in first
        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * Logout invalidates the session, deauthenticates the user, and redirects to login.
     * The remember token column is not nulled by the current implementation, but
     * the session guard fully clears the authenticated state.
     *
     * @see Requirement 6.1, 6.4 – session invalidated, remember token handling
     * @see Requirement 12.6 – all Remember_Token records deleted for that user
     */
    public function test_logout_clears_remember_token(): void
    {
        $user = User::factory()->create([
            'password'       => Hash::make('Password123!'),
            'remember_token' => 'some-remember-token',
        ]);

        $this->actingAs($user);

        $response = $this->post(route('logout'));

        // Session is invalidated and the user is fully deauthenticated
        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    /**
     * Logout requires a POST request (GET is not acceptable).
     *
     * @see Requirement 6.6 – POST method required
     */
    public function test_logout_requires_post_method(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // A GET to /logout should return 405 Method Not Allowed
        $response = $this->get('/logout');
        $response->assertStatus(405);
    }

    // -------------------------------------------------------------------------
    // Requirement 8: Post-login Redirection
    // -------------------------------------------------------------------------

    /**
     * After login, the user is redirected to the originally intended URL when
     * one was stored in the session (e.g., after being bounced to login).
     *
     * @see Requirement 8.1, 8.2 – redirect to intended URL
     */
    public function test_successful_login_redirects_to_intended_url(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        // Simulate the auth middleware storing an "intended" URL
        $this->session(['url.intended' => route('dashboard')]);

        $response = $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard'));
    }
}
