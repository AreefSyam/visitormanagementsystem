<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ValidateSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ValidateSessionTest extends TestCase
{
    use RefreshDatabase;

    private ValidateSession $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ValidateSession();

        // Define login route for testing
        Route::get('/login', fn() => response('Login Page'))->name('login');
    }

    #[Test]
    public function it_allows_guest_requests_to_pass_through(): void
    {
        $request = Request::create('/test', 'GET');
        $next = fn($req) => response('OK');

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('OK', $response->getContent());
    }

    #[Test]
    public function it_allows_valid_session_to_pass_through(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app['session.store']);

        $sessionId = $request->session()->getId();
        $userAgent = 'Mozilla/5.0 Test Browser';
        $currentTime = time();

        // Create session record in database
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => $userAgent,
            'payload' => '',
            'last_activity' => $currentTime,
        ]);

        $request->headers->set('User-Agent', $userAgent);

        $next = fn($req) => response('OK');
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('OK', $response->getContent());

        // Verify last_activity was updated
        $updatedSession = DB::table('sessions')->where('id', $sessionId)->first();
        $this->assertGreaterThanOrEqual($currentTime, $updatedSession->last_activity);
    }

    #[Test]
    public function it_destroys_session_when_session_not_found_in_database(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app['session.store']);

        // Don't create session record in database
        $next = fn($req) => response('OK');
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
        $this->assertFalse(Auth::check());
    }

    #[Test]
    public function it_destroys_session_when_user_agent_mismatches(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app['session.store']);

        $sessionId = $request->session()->getId();
        $currentTime = time();

        // Create session record with different user agent
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Original Browser',
            'payload' => '',
            'last_activity' => $currentTime,
        ]);

        // Request with different user agent
        $request->headers->set('User-Agent', 'Different Browser');

        $next = fn($req) => response('OK');
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
        $this->assertFalse(Auth::check());
    }

    #[Test]
    public function it_destroys_session_when_session_exceeds_timeout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app['session.store']);

        $sessionId = $request->session()->getId();
        $userAgent = 'Mozilla/5.0 Test Browser';

        // Set last_activity to 121 minutes ago (exceeds 120 minute timeout)
        $expiredTime = time() - (121 * 60);

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => $userAgent,
            'payload' => '',
            'last_activity' => $expiredTime,
        ]);

        $request->headers->set('User-Agent', $userAgent);

        $next = fn($req) => response('OK');
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
        $this->assertFalse(Auth::check());

        // Check for session expired message
        $this->assertEquals(
            'Your session has expired. Please log in again.',
            session('status')
        );
    }

    #[Test]
    public function it_allows_session_within_timeout_period(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app['session.store']);

        $sessionId = $request->session()->getId();
        $userAgent = 'Mozilla/5.0 Test Browser';

        // Set last_activity to 119 minutes ago (within 120 minute timeout)
        $validTime = time() - (119 * 60);

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => $userAgent,
            'payload' => '',
            'last_activity' => $validTime,
        ]);

        $request->headers->set('User-Agent', $userAgent);

        $next = fn($req) => response('OK');
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('OK', $response->getContent());
        $this->assertTrue(Auth::check());
    }
}
