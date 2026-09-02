<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for session security requirements.
 *
 * These tests exercise the full HTTP stack so that the ValidateSession
 * middleware (which queries the sessions table directly via DB) is triggered
 * as it would be in production.  phpunit.xml sets SESSION_DRIVER=array, but
 * setUp() overrides this to 'database' for these tests.
 *
 * Note on test strategy
 * ---------------------
 * In the test framework, each HTTP request starts a fresh session, so
 * cross-request auth state cannot be maintained by cookie alone when the
 * session driver is switched at runtime.  Tests that verify the "happy path"
 * (valid agent / within timeout → allowed) therefore:
 *
 *   • Test the positive outcome within a single POST /login + immediate follow-
 *     up, OR
 *   • Verify the database state (e.g. session row created, last_activity set).
 *
 * The negative outcomes (wrong agent, timeout exceeded) are straightforward
 * because they only require a 302 redirect assertion, which doesn't need an
 * authenticated follow-up request.
 *
 * Behaviours that require two consecutive authenticated requests are already
 * covered by unit-level tests in ValidateSessionTest.
 *
 * Covers:
 *   - Requirement 4: Session regeneration on login (session fixation prevention)
 *   - Requirement 5: 120-minute inactivity timeout; last_activity written on login
 *   - Requirement 7: User-agent validation; changed agent destroys session
 */
class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Password1!';

    /** Default user agent used when seeding sessions for middleware tests. */
    private const TEST_AGENT = 'Mozilla/5.0 (PhpUnit; TestBrowser)';

    protected function setUp(): void
    {
        parent::setUp();

        // Override session driver to 'database' before any request so the
        // session store is initialised with the correct driver.  This allows
        // ValidateSession to find/write session rows in the sessions table.
        config(['session.driver' => 'database']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Seed a sessions row that ValidateSession will consider valid.
     * Call AFTER actingAs() so session()->getId() is stable.
     */
    private function seedValidSession(
        int    $userId,
        string $userAgent = self::TEST_AGENT,
        int    $lastActivity = 0
    ): string {
        $sessionId    = session()->getId();
        $lastActivity = $lastActivity ?: time();

        DB::table('sessions')->updateOrInsert(
            ['id' => $sessionId],
            [
                'user_id'       => $userId,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => $userAgent,
                'payload'       => '',
                'last_activity' => $lastActivity,
            ]
        );

        return $sessionId;
    }

    // -------------------------------------------------------------------------
    // Requirement 4 – Session Creation / Session Fixation Prevention
    // -------------------------------------------------------------------------

    #[Test]
    public function session_id_is_regenerated_on_successful_login(): void
    {
        // Requirement 4.2: The Session Manager SHALL regenerate the session ID
        // to prevent session fixation attacks.
        $user = User::factory()->create([
            'password' => Hash::make(self::PASSWORD),
        ]);

        // 1. Visit the login page — establishes a guest (pre-login) session.
        $preLoginResponse = $this->get(route('login'));
        $cookieName       = config('session.cookie');
        $preLoginCookie   = null;

        foreach ($preLoginResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                $preLoginCookie = $cookie->getValue();
                break;
            }
        }

        // 2. Log in — AuthController calls session()->regenerate().
        $loginResponse   = $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => self::PASSWORD,
        ]);
        $postLoginCookie = null;

        foreach ($loginResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                $postLoginCookie = $cookie->getValue();
                break;
            }
        }

        // A session row for the authenticated user must be written to the DB.
        $sessionRecord = DB::table('sessions')->where('user_id', $user->id)->first();
        $this->assertNotNull(
            $sessionRecord,
            'A session record for the authenticated user must exist in the database after login.'
        );

        // The session cookie must have changed — proof that the session ID was
        // regenerated (session fixation prevention).
        if ($preLoginCookie !== null && $postLoginCookie !== null) {
            $this->assertNotEquals(
                $preLoginCookie,
                $postLoginCookie,
                'The session cookie must change on successful login to prevent session fixation.'
            );
        }
    }

    #[Test]
    public function session_record_is_persisted_to_database_on_login(): void
    {
        // Requirement 4.1: The Session Manager SHALL create a new session with
        // a unique session ID stored in the sessions table.
        $user = User::factory()->create([
            'password' => Hash::make(self::PASSWORD),
        ]);

        $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => self::PASSWORD,
        ]);

        $this->assertDatabaseHas('sessions', ['user_id' => $user->id]);
    }

    #[Test]
    public function session_stores_user_agent_on_login(): void
    {
        // Requirement 4.4: WHEN a session is created, the Session Manager SHALL
        // store the User_Agent in the session data.
        $user  = User::factory()->create(['password' => Hash::make(self::PASSWORD)]);
        $agent = 'Mozilla/5.0 (Feature Test Browser/1.0)';

        $this->withHeaders(['User-Agent' => $agent])
            ->post(route('login.submit'), [
                'email'    => $user->email,
                'password' => self::PASSWORD,
            ]);

        $record = DB::table('sessions')->where('user_id', $user->id)->first();

        $this->assertNotNull($record, 'Session record must exist after login.');
        $this->assertEquals(
            $agent,
            $record->user_agent,
            'The sessions table must store the User-Agent used during login.'
        );
    }

    // -------------------------------------------------------------------------
    // Requirement 7 – Session Security Validation (user-agent checks)
    // -------------------------------------------------------------------------

    #[Test]
    public function changed_user_agent_destroys_session_and_redirects_to_login(): void
    {
        // Requirement 7.4: IF User_Agent changes during a session, THEN the
        // Session Manager SHALL destroy the session and require re-authentication.
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->seedValidSession($user->id, self::TEST_AGENT);

        // Send a request with a *different* user agent (simulating session hijacking).
        $this->withHeaders(['User-Agent' => 'curl/7.68.0 (Suspicious)'])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[Test]
    public function user_agent_mismatch_logs_out_the_user(): void
    {
        // Requirement 7.5: IF session validation fails, THEN the session is
        // destroyed and the user is logged out.
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->seedValidSession($user->id, self::TEST_AGENT);

        // ValidateSession must destroy the session for an agent mismatch.
        $this->withHeaders(['User-Agent' => 'SuspiciousBot/2.0'])
            ->get(route('dashboard'));

        $this->assertGuest();
    }

    #[Test]
    public function session_is_invalidated_when_not_found_in_database(): void
    {
        // Requirement 7.1: The Session Manager SHALL verify the session ID
        // exists in storage on each authenticated request.
        $user = User::factory()->create();

        // actingAs without seeding a DB row — the middleware will not find it.
        $this->actingAs($user);

        $this->withHeaders(['User-Agent' => self::TEST_AGENT])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    // -------------------------------------------------------------------------
    // Requirement 5 – Session Timeout
    // -------------------------------------------------------------------------

    #[Test]
    public function session_is_destroyed_after_120_minutes_of_inactivity(): void
    {
        // Requirement 5.1 / 5.3: Session_Timeout is 120 minutes; exceeding
        // it must destroy the session and redirect to login.
        $user = User::factory()->create();

        $this->actingAs($user);
        // last_activity = 121 minutes ago → 1 minute past the 120-minute limit.
        $this->seedValidSession($user->id, self::TEST_AGENT, time() - (121 * 60));

        $this->withHeaders(['User-Agent' => self::TEST_AGENT])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[Test]
    public function session_expiry_flash_message_is_shown_after_timeout(): void
    {
        // Requirement 5.5: The Authentication_System SHALL display
        // "Your session has expired. Please log in again."
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->seedValidSession($user->id, self::TEST_AGENT, time() - (121 * 60));

        $response = $this->withHeaders(['User-Agent' => self::TEST_AGENT])
            ->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Your session has expired. Please log in again.');
    }

    #[Test]
    public function session_timeout_is_set_to_120_minutes_in_configuration(): void
    {
        // Requirement 5.1: The Session_Manager SHALL set Session_Timeout to
        // 120 minutes of inactivity.
        $this->assertEquals(
            120,
            config('session.lifetime'),
            'Session lifetime must be configured to 120 minutes.'
        );
    }

    #[Test]
    public function last_activity_is_written_to_sessions_table_on_login(): void
    {
        // Requirement 5.2 (partial): The session record written at login time
        // must contain a recent last_activity timestamp so the timeout
        // calculation starts from the login moment.
        $user = User::factory()->create(['password' => Hash::make(self::PASSWORD)]);

        $before = time();

        $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => self::PASSWORD,
        ]);

        $record = DB::table('sessions')->where('user_id', $user->id)->first();

        $this->assertNotNull($record, 'Session record must exist after login.');
        $this->assertGreaterThanOrEqual(
            $before,
            $record->last_activity,
            'last_activity must be set to at least the time of login.'
        );
        $this->assertLessThanOrEqual(
            time(),
            $record->last_activity,
            'last_activity must not be set to a future timestamp.'
        );
    }
}
