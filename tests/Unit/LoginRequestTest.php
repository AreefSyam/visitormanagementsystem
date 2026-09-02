<?php

namespace Tests\Unit;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LoginRequestTest extends TestCase
{
    /**
     * Test validation rules require email field.
     */
    public function test_email_field_is_required(): void
    {
        $request = new LoginRequest();
        $validator = Validator::make(
            ['password' => 'password123'],
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
        $this->assertEquals('Please enter your email address.', $validator->errors()->first('email'));
    }

    /**
     * Test validation rules require valid email format.
     */
    public function test_email_field_must_be_valid_format(): void
    {
        $request = new LoginRequest();
        $validator = Validator::make(
            ['email' => 'invalid-email', 'password' => 'password123'],
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
        $this->assertEquals('Please enter a valid email address.', $validator->errors()->first('email'));
    }

    /**
     * Test validation rules require password field.
     */
    public function test_password_field_is_required(): void
    {
        $request = new LoginRequest();
        $validator = Validator::make(
            ['email' => 'user@example.com'],
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
        $this->assertEquals('Please enter your password.', $validator->errors()->first('password'));
    }

    /**
     * Test validation rules require password minimum length of 8 characters.
     */
    public function test_password_field_must_be_at_least_8_characters(): void
    {
        $request = new LoginRequest();
        $validator = Validator::make(
            ['email' => 'user@example.com', 'password' => 'short'],
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
        $this->assertEquals('Password must be at least 8 characters.', $validator->errors()->first('password'));
    }

    /**
     * Test validation passes with valid inputs.
     */
    public function test_validation_passes_with_valid_inputs(): void
    {
        $request = new LoginRequest();
        $validator = Validator::make(
            [
                'email' => 'user@example.com',
                'password' => 'password123',
                'remember' => true,
            ],
            $request->rules(),
            $request->messages()
        );

        $this->assertFalse($validator->fails());
    }

    /**
     * Test throttle key generation uses lowercase email.
     */
    public function test_throttle_key_uses_lowercase_email(): void
    {
        $request = new LoginRequest();
        $request->merge(['email' => 'User@Example.COM']);

        $throttleKey = $request->throttleKey();

        $this->assertEquals('login_attempts:user@example.com', $throttleKey);
    }

    /**
     * Test authorize method returns true.
     */
    public function test_authorize_returns_true(): void
    {
        $request = new LoginRequest();

        $this->assertTrue($request->authorize());
    }
}
