<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminMiddlewareIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Register a test route with admin middleware
        Route::get('/test-admin-only', function () {
            return response()->json(['message' => 'Admin access granted']);
        })->middleware('admin');
    }

    /**
     * Test that admin middleware alias is registered and works.
     */
    public function test_admin_middleware_alias_is_registered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/test-admin-only');

        $response->assertOk()
            ->assertJson(['message' => 'Admin access granted']);
    }

    /**
     * Test that non-admin users are blocked.
     */
    public function test_non_admin_users_are_blocked(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get('/test-admin-only');

        $response->assertForbidden();
    }
}
