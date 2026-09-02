<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailVerificationService();
    }

    /**
     * Test that sendVerificationEmail logs the event.
     *
     * **Validates: Requirements 20.4, 26.1**
     */
    public function test_send_verification_email_logs_event(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Email verification sent', \Mockery::on(function ($context) {
                return isset($context['email'])
                    && isset($context['ip_address'])
                    && isset($context['timestamp']);
            }));

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->service->sendVerificationEmail($user);
    }

    /**
     * Test that verify marks email as verified and logs the event.
     *
     * **Validates: Requirements 20.10, 26.4**
     */
    public function test_verify_marks_email_as_verified_and_logs(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Email verified successfully', \Mockery::on(function ($context) {
                return isset($context['email'])
                    && isset($context['timestamp']);
            }));

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->assertNull($user->email_verified_at);

        $this->service->verify($user);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    /**
     * Test that verify does not re-verify already verified email.
     *
     * **Validates: Requirements 20.10**
     */
    public function test_verify_does_not_reverify_already_verified_email(): void
    {
        Log::shouldReceive('info')->never();

        $user = User::factory()->create([
            'email_verified_at' => now()->subDay(),
        ]);

        $originalVerifiedAt = $user->email_verified_at;

        $this->service->verify($user);

        $user->refresh();
        $this->assertEquals($originalVerifiedAt->toDateTimeString(), $user->email_verified_at->toDateTimeString());
    }

    /**
     * Test that generateVerificationToken creates a valid signed URL with 24-hour expiration.
     *
     * **Validates: Requirements 20.1, 20.3, 20.5**
     */
    public function test_generate_verification_token_creates_valid_signed_url(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $url = $this->service->generateVerificationToken($user);

        // Verify URL structure
        $this->assertStringContainsString('email/verify', $url);
        $this->assertStringContainsString('signature', $url);
        $this->assertStringContainsString('expires', $url);

        // Verify the URL contains user ID and email hash
        $this->assertStringContainsString((string) $user->id, $url);
        $this->assertStringContainsString(sha1($user->email), $url);

        // Verify the signed URL is valid by creating a request from it
        $request = \Illuminate\Http\Request::create($url);
        $this->assertTrue(URL::hasValidSignature($request));
    }

    /**
     * Test that generateVerificationToken includes expiration time of 24 hours.
     *
     * **Validates: Requirements 20.3**
     */
    public function test_generate_verification_token_has_24_hour_expiration(): void
    {
        $user = User::factory()->create();

        $url = $this->service->generateVerificationToken($user);

        // Parse URL to get expiration timestamp
        parse_str(parse_url($url, PHP_URL_QUERY), $params);

        $this->assertArrayHasKey('expires', $params);

        $expirationTime = (int) $params['expires'];
        $expectedExpiration = now()->addHours(24)->timestamp;

        // Allow 5 second tolerance for test execution time
        $this->assertEqualsWithDelta($expectedExpiration, $expirationTime, 5);
    }
}
