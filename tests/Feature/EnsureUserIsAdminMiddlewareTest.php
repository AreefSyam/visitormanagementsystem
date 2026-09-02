<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureUserIsAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that admin user can pass through middleware.
     */
    public function test_admin_user_can_pass_through_middleware(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $admin);

        $middleware = new EnsureUserIsAdmin();

        $response = $middleware->handle($request, function ($req) {
            return response('Success');
        });

        $this->assertEquals('Success', $response->getContent());
    }

    /**
     * Test that regular user is blocked by middleware.
     */
    public function test_regular_user_is_blocked_by_middleware(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Unauthorized access');

        $user = User::factory()->create(['role' => 'user']);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);

        $middleware = new EnsureUserIsAdmin();

        $middleware->handle($request, function ($req) {
            return response('Success');
        });
    }

    /**
     * Test that guest user is blocked by middleware.
     */
    public function test_guest_is_blocked_by_middleware(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Unauthorized access');

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => null);

        $middleware = new EnsureUserIsAdmin();

        $middleware->handle($request, function ($req) {
            return response('Success');
        });
    }

    /**
     * Test that user without role field is blocked.
     */
    public function test_user_without_admin_role_is_blocked(): void
    {
        $this->expectException(HttpException::class);

        $user = User::factory()->create(['role' => 'moderator']);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn() => $user);

        $middleware = new EnsureUserIsAdmin();

        $middleware->handle($request, function ($req) {
            return response('Success');
        });
    }
}
