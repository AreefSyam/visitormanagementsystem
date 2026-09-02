<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AddRoleToUsersTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_role_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('users', 'role'),
            'Users table should have a role column'
        );
    }

    public function test_role_column_has_default_value(): void
    {
        $user = \App\Models\User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->assertEquals('user', $user->role, 'Role column should default to "user"');
    }

    public function test_role_column_accepts_admin_value(): void
    {
        $admin = \App\Models\User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->assertEquals('admin', $admin->role, 'Role column should accept "admin" value');
    }

    public function test_role_column_has_index(): void
    {
        $indexes = Schema::getIndexes('users');
        $roleIndexExists = collect($indexes)->contains(function ($index) {
            return in_array('role', $index['columns']);
        });

        $this->assertTrue($roleIndexExists, 'Role column should have an index');
    }
}
