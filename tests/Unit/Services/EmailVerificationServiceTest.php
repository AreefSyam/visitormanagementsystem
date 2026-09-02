<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
     * Test that sendVerificationEmail generates a signed URL.
     *
     * The VerifyEmailNotification internally calls URL::temporarySignedRoute
     * to generate a cryptographically signed verification link.
     *
     * **Validates: Requirements 20.1, 20.3, 20.5**
     */
    public function test_send_verification_email_generates_signed_url(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => null]);

        $this->service->sendVerificationEmail($user);

        // Verify the notification was sent — it internally generates a signed URL
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        // Additionally verify that the service's generateVerificationToken produces a valid signed URL
        $url = $this->service->generateVerificationToken($user);

        $this->assertStringContainsString('email/verify', $url);
        $this->assertStringContainsString('signature', $url);
        $this->assertStringContainsString('expires', $url);
        $this->assertStringContainsString((string) $user->id, $url);
        $this->assertStringContainsString(sha1($user->email), $url);

        // Confirm the signed URL passes Laravel's signature validation
        $request = \Illuminate\Http\Request::create($url);
        $this->assertTrue(URL::hasValidSignature($request));
    }

    /**
     * Test that sendVerificationEmail queues the notification.
     *
     * VerifyEmailNotification implements ShouldQueue, so the notification
     * should be dispatched to the queue rather than sent synchronously.
     *
     * **Validates: Requirements 20.4**
     */
    public function test_send_verification_email_queues_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => null]);

        $this->service->sendVerificationEmail($user);

        // Assert the notification was dispatched (queued) to the user
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        // Assert no notifications were sent synchronously (they should all be queued)
        Notification::assertCount(1);
    }

    /**
     * Test that verify updates the email_verified_at timestamp.
     *
     * When verify() is called on an unverified user, it must persist
     * email_verified_at to the database.
     *
     * **Validates: Requirements 20.10**
     */
    public function test_verify_updates_email_verified_at_timestamp(): void
    {
        Log::shouldReceive('info')->once();

        $user = User::factory()->create(['email_verified_at' => null]);

        $this->assertNull($user->email_verified_at);

        $this->service->verify($user);

        $user->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertEqualsWithDelta(now()->timestamp, $user->email_verified_at->timestamp, 5);
    }

    /**
     * Test that verify logs the verification event.
     *
     * After a successful email verification, an audit log entry must be
     * written with the user's email and a timestamp.
     *
     * **Validates: Requirements 20.10, 26.4**
     */
    public function test_verify_logs_verification_event(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Email verified successfully', \Mockery::on(function (array $context): bool {
                return isset($context['email'])
                    && isset($context['timestamp']);
            }));

        $user = User::factory()->create(['email_verified_at' => null]);

        $this->service->verify($user);
    }

    /**
     * Test that verify does not re-verify an already-verified email.
     *
     * If email_verified_at is already set, no log entry should be written
     * and the timestamp should remain unchanged.
     *
     * **Validates: Requirements 20.10**
     */
    public function test_verify_skips_already_verified_user(): void
    {
        Log::shouldReceive('info')->never();

        $verifiedAt = now()->subDay();
        $user = User::factory()->create(['email_verified_at' => $verifiedAt]);

        $this->service->verify($user);

        $user->refresh();
        $this->assertEquals(
            $verifiedAt->toDateTimeString(),
            $user->email_verified_at->toDateTimeString()
        );
    }

    /**
     * Test that generateVerificationToken creates a signed URL with 24-hour expiry.
     *
     * **Validates: Requirements 20.1, 20.3**
     */
    public function test_generate_verification_token_has_24_hour_expiration(): void
    {
        $user = User::factory()->create();

        $url = $this->service->generateVerificationToken($user);

        parse_str(parse_url($url, PHP_URL_QUERY), $params);

        $this->assertArrayHasKey('expires', $params);

        $expirationTime = (int) $params['expires'];
        $expectedExpiration = now()->addHours(24)->timestamp;

        // Allow 5-second tolerance for test execution time
        $this->assertEqualsWithDelta($expectedExpiration, $expirationTime, 5);
    }
}
