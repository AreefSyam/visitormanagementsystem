<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Password Reset Flow Tests
 *
 * Covers Requirements 24 (Password Reset Email Process) and
 * 25 (Password Reset Form Process).
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Requirement 24 – Password Reset Email Process
    // -----------------------------------------------------------------------

    /**
     * Req 24.7 / 24.12 – When a valid email is submitted the system sends a
     * reset link and returns a generic success message.
     */
    #[Test]
    public function forgot_password_request_sends_reset_email_for_existing_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'user@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify a reset token was stored for the user
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);
    }

    /**
     * Req 24.13 – Non-existent emails still show a generic success message
     * so as not to reveal whether an account exists.
     */
    #[Test]
    public function forgot_password_with_non_existent_email_shows_generic_success(): void
    {
        $response = $this->post(route('password.email'), [
            'email' => 'nobody@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // No token should have been stored for an unknown address
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'nobody@example.com',
        ]);
    }

    /**
     * Req 24.14 – Password reset requests are rate-limited to 3 per hour.
     */
    #[Test]
    public function password_reset_rate_limiting_blocks_after_three_attempts(): void
    {
        User::factory()->create(['email' => 'ratelimit@example.com']);

        $throttleKey = 'password_reset:ratelimit@example.com';
        RateLimiter::clear($throttleKey);

        // Exhaust the 3-attempt limit
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit($throttleKey, 3600);
        }

        $response = $this->post(route('password.email'), [
            'email' => 'ratelimit@example.com',
        ]);

        // Should be blocked – validation exception redirects back with errors
        $response->assertSessionHasErrors('email');

        $errorMessage = session('errors')->first('email');
        $this->assertStringContainsString('Too many password reset requests', $errorMessage);

        RateLimiter::clear($throttleKey);
    }

    // -----------------------------------------------------------------------
    // Requirement 25 – Password Reset Form Process
    // -----------------------------------------------------------------------

    /**
     * Req 25.1-2 – A valid reset link shows the password reset form.
     */
    #[Test]
    public function valid_reset_link_displays_password_reset_form(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $token = Password::createToken($user);

        $response = $this->get(route('password.reset', [
            'token' => $token,
            'email' => 'user@example.com',
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('auth.reset-password');
        $response->assertViewHas('token', $token);
        $response->assertViewHas('email', 'user@example.com');
    }

    /**
     * Req 25.12-13 – A valid token allows the user to reset their password.
     */
    #[Test]
    public function valid_token_allows_password_reset(): void
    {
        $user = User::factory()->create([
            'email'    => 'user@example.com',
            'password' => Hash::make('OldPassword1!'),
        ]);

        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token'                 => $token,
            'email'                 => 'user@example.com',
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

        $response->assertRedirect(route('login'));

        // Verify the password was actually updated in the database
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword1!', $user->password));
    }

    /**
     * Req 25.17-18 – An expired token redirects to the request form with an
     * error message and the reset-password view includes a "Request New Link"
     * link in the fallback section.
     */
    #[Test]
    public function expired_token_shows_error_and_request_new_link_button(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        // Insert an expired token record (created 2 hours ago)
        \DB::table('password_reset_tokens')->insert([
            'email'      => 'user@example.com',
            'token'      => Hash::make('expiredtoken'),
            'created_at' => Carbon::now()->subHours(2),
        ]);

        $response = $this->get(route('password.reset', [
            'token' => 'expiredtoken',
            'email' => 'user@example.com',
        ]));

        // Controller redirects back to the request form with an error flash
        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('error');

        $errorMessage = $response->getSession()->get('error');
        $this->assertStringContainsString('expired', strtolower($errorMessage));

        // The reset-password view always shows a "Request New Link" fallback section
        // regardless of whether the token is expired (per the blade template).
        $validUser  = User::factory()->create(['email' => 'user2@example.com']);
        $validToken = Password::createToken($validUser);

        $resetFormResponse = $this->get(route('password.reset', [
            'token' => $validToken,
            'email' => 'user2@example.com',
        ]));

        $resetFormResponse->assertStatus(200);
        $resetFormResponse->assertSee('Request New Link');
    }

    /**
     * Req 25.15 – Successful reset clears the remember_token for that user.
     */
    #[Test]
    public function successful_reset_deletes_remember_tokens(): void
    {
        $user = User::factory()->create([
            'email'          => 'user@example.com',
            'remember_token' => 'old-remember-token',
        ]);

        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token'                 => $token,
            'email'                 => 'user@example.com',
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

        $user->refresh();

        // The remember_token should have been cleared after the reset
        $this->assertNull($user->remember_token);
    }

    /**
     * Req 25.16 – Successful reset redirects to the login page with a success
     * flash message.
     */
    #[Test]
    public function successful_reset_redirects_to_login_with_success_message(): void
    {
        $user = User::factory()->create([
            'email'    => 'user@example.com',
            'password' => Hash::make('OldPassword1!'),
        ]);

        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token'                 => $token,
            'email'                 => 'user@example.com',
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');
    }
}
