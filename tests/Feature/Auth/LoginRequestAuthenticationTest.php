<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoginRequestAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test authenticate method succeeds with valid credentials.
     */
    public function test_authenticate_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'user@example.com',
            'password' => 'Password123!',
        ]);

        $result = $request->authenticate();

        $this->assertTrue($result);
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Test authenticate method fails with invalid credentials.
     */
    public function test_authenticate_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'user@example.com',
            'password' => 'WrongPassword',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('These credentials do not match our records.');

        $request->authenticate();
    }

    /**
     * Test authenticate method increments rate limiter on failed attempt.
     */
    public function test_authenticate_increments_rate_limiter_on_failure(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'user@example.com',
            'password' => 'WrongPassword',
        ]);

        $throttleKey = 'login_attempts:user@example.com';
        
        // Clear any existing attempts
        RateLimiter::clear($throttleKey);

        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            // Expected
        }

        $this->assertEquals(1, RateLimiter::attempts($throttleKey));
    }

    /**
     * Test authenticate method clears rate limiter on success.
     */
    public function test_authenticate_clears_rate_limiter_on_success(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $throttleKey = 'login_attempts:user@example.com';
        
        // Simulate previous failed attempts
        RateLimiter::hit($throttleKey);
        RateLimiter::hit($throttleKey);
        
        $this->assertEquals(2, RateLimiter::attempts($throttleKey));

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'user@example.com',
            'password' => 'Password123!',
        ]);

        $request->authenticate();

        $this->assertEquals(0, RateLimiter::attempts($throttleKey));
    }

    /**
     * Test rate limiting blocks after 5 failed attempts.
     */
    public function test_rate_limiting_blocks_after_five_attempts(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $throttleKey = 'login_attempts:user@example.com';
        
        // Clear any existing attempts
        RateLimiter::clear($throttleKey);

        // Simulate 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey, 60);
        }

        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'user@example.com',
            'password' => 'Password123!',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/Too many login attempts\. Please try again in \d+ seconds\./');

        $request->authenticate();
    }

    /**
     * Test ensureIsNotRateLimited allows request when under limit.
     */
    public function test_ensure_is_not_rate_limited_allows_request_under_limit(): void
    {
        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'user@example.com',
            'password' => 'Password123!',
        ]);

        $throttleKey = 'login_attempts:user@example.com';
        RateLimiter::clear($throttleKey);

        // Should not throw exception
        $request->ensureIsNotRateLimited();
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Test ensureIsNotRateLimited blocks request when limit exceeded.
     */
    public function test_ensure_is_not_rate_limited_blocks_when_limit_exceeded(): void
    {
        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'user@example.com',
            'password' => 'Password123!',
        ]);

        $throttleKey = 'login_attempts:user@example.com';
        RateLimiter::clear($throttleKey);

        // Simulate 6 attempts (over the limit of 5)
        for ($i = 0; $i < 6; $i++) {
            RateLimiter::hit($throttleKey, 60);
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/Too many login attempts\./');

        $request->ensureIsNotRateLimited();
    }
}
