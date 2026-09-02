<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that isAdmin returns true when user has admin role.
     */
    public function test_is_admin_returns_true_for_admin_user(): void
    {
        // Arrange
        $adminUser = User::factory()->create([
            'role' => 'admin'
        ]);

        // Act
        $result = $adminUser->isAdmin();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test that isAdmin returns false when user has non-admin role.
     */
    public function test_is_admin_returns_false_for_regular_user(): void
    {
        // Arrange
        $regularUser = User::factory()->create([
            'role' => 'user'
        ]);

        // Act
        $result = $regularUser->isAdmin();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test that isAdmin returns false when user has default role.
     */
    public function test_is_admin_returns_false_for_default_role(): void
    {
        // Arrange
        // Create user without specifying role - should use default 'user'
        $userWithDefaultRole = User::factory()->create();

        // Act
        $result = $userWithDefaultRole->isAdmin();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test that isAdmin returns false when user has empty string role.
     */
    public function test_is_admin_returns_false_for_empty_role(): void
    {
        // Arrange
        $userWithEmptyRole = User::factory()->create([
            'role' => ''
        ]);

        // Act
        $result = $userWithEmptyRole->isAdmin();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test that isAdmin returns false when user has a different role.
     */
    public function test_is_admin_returns_false_for_other_roles(): void
    {
        // Arrange
        $moderatorUser = User::factory()->create([
            'role' => 'moderator'
        ]);

        // Act
        $result = $moderatorUser->isAdmin();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test that isAdmin is case-sensitive.
     */
    public function test_is_admin_is_case_sensitive(): void
    {
        // Arrange
        $userWithUppercaseRole = User::factory()->create([
            'role' => 'Admin'
        ]);

        // Act
        $result = $userWithUppercaseRole->isAdmin();

        // Assert
        $this->assertFalse($result, 'isAdmin should be case-sensitive and only match "admin" exactly');
    }
}
