<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Valid registration data helper
    // -------------------------------------------------------------------------

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms'                 => '1',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Requirement 17 & 19: Successful registration creates user record
    // -------------------------------------------------------------------------

    /**
     * Requirement 17, 19: Valid registration data creates a new user record.
     */
    public function test_successful_registration_with_valid_data_creates_user(): void
    {
        Notification::fake();

        $response = $this->post(route('register.submit'), $this->validData());

        $response->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'name'              => 'John Doe',
            'email'             => 'john@example.com',
            'email_verified_at' => null,
        ]);
    }

    /**
     * Requirement 19: Email is stored in lowercase regardless of input casing.
     */
    public function test_registration_stores_email_in_lowercase(): void
    {
        Notification::fake();

        $this->post(route('register.submit'), $this->validData([
            'email' => 'John@EXAMPLE.COM',
        ]));

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    /**
     * Requirement 19: email_verified_at is null immediately after registration.
     */
    public function test_registration_sets_email_verified_at_to_null(): void
    {
        Notification::fake();

        $this->post(route('register.submit'), $this->validData());

        $user = User::where('email', 'john@example.com')->first();
        $this->assertNull($user->email_verified_at);
    }

    // -------------------------------------------------------------------------
    // Requirement 20: Registration sends verification email
    // -------------------------------------------------------------------------

    /**
     * Requirement 20: Successful registration triggers a verification email.
     */
    public function test_registration_sends_email_verification_notification(): void
    {
        Notification::fake();

        $this->post(route('register.submit'), $this->validData());

        $user = User::where('email', 'john@example.com')->first();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    /**
     * Requirement 19, 20: Success flash message is set after registration.
     */
    public function test_registration_sets_success_flash_message(): void
    {
        Notification::fake();

        $response = $this->post(route('register.submit'), $this->validData());

        $response->assertSessionHas('success');
    }

    // -------------------------------------------------------------------------
    // Requirement 17: Duplicate email shows validation error
    // -------------------------------------------------------------------------

    /**
     * Requirement 17.6, 17.11: Registration with an already-registered email fails.
     */
    public function test_registration_with_existing_email_shows_error(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->post(route('register.submit'), $this->validData());

        $response->assertSessionHasErrors('email');
        $this->assertEquals(
            'This email address is already registered.',
            session('errors')->first('email')
        );
    }

    /**
     * Requirement 17.6: Duplicate-email check is case-insensitive (email normalised to lowercase).
     */
    public function test_registration_with_existing_email_different_case_shows_error(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->post(route('register.submit'), $this->validData([
            'email' => 'JOHN@EXAMPLE.COM',
        ]));

        $response->assertSessionHasErrors('email');
    }

    // -------------------------------------------------------------------------
    // Requirement 18: Weak password shows validation error
    // -------------------------------------------------------------------------

    /**
     * Requirement 18: Password missing uppercase letter fails validation.
     */
    public function test_registration_with_password_missing_uppercase_fails(): void
    {
        $response = $this->post(route('register.submit'), $this->validData([
            'password'              => 'password123!',
            'password_confirmation' => 'password123!',
        ]));

        $response->assertSessionHasErrors('password');
    }

    /**
     * Requirement 18: Password missing lowercase letter fails validation.
     */
    public function test_registration_with_password_missing_lowercase_fails(): void
    {
        $response = $this->post(route('register.submit'), $this->validData([
            'password'              => 'PASSWORD123!',
            'password_confirmation' => 'PASSWORD123!',
        ]));

        $response->assertSessionHasErrors('password');
    }

    /**
     * Requirement 18: Password missing a number fails validation.
     */
    public function test_registration_with_password_missing_number_fails(): void
    {
        $response = $this->post(route('register.submit'), $this->validData([
            'password'              => 'PasswordABC!',
            'password_confirmation' => 'PasswordABC!',
        ]));

        $response->assertSessionHasErrors('password');
    }

    /**
     * Requirement 18: Password missing a special character fails validation.
     */
    public function test_registration_with_password_missing_special_character_fails(): void
    {
        $response = $this->post(route('register.submit'), $this->validData([
            'password'              => 'Password1234',
            'password_confirmation' => 'Password1234',
        ]));

        $response->assertSessionHasErrors('password');
    }

    /**
     * Requirement 18.5: Password shorter than 8 characters fails validation.
     */
    public function test_registration_with_password_too_short_fails(): void
    {
        $response = $this->post(route('register.submit'), $this->validData([
            'password'              => 'P1!aB',
            'password_confirmation' => 'P1!aB',
        ]));

        $response->assertSessionHasErrors('password');
    }

    /**
     * Requirement 17.9: Mismatched password and confirmation fails validation.
     */
    public function test_registration_with_mismatched_password_confirmation_fails(): void
    {
        $response = $this->post(route('register.submit'), $this->validData([
            'password'              => 'Password123!',
            'password_confirmation' => 'Different123!',
        ]));

        $response->assertSessionHasErrors('password');
    }

    // -------------------------------------------------------------------------
    // Requirement 17: Terms of Service must be accepted
    // -------------------------------------------------------------------------

    /**
     * Requirement 17.10: Registration without accepting terms fails.
     */
    public function test_registration_without_terms_acceptance_fails(): void
    {
        $data = $this->validData();
        unset($data['terms']);

        $response = $this->post(route('register.submit'), $data);

        $response->assertSessionHasErrors('terms');
    }

    /**
     * Requirement 17.10: Explicitly unchecked terms (value '0') fails.
     */
    public function test_registration_with_terms_unchecked_fails(): void
    {
        $response = $this->post(route('register.submit'), $this->validData([
            'terms' => '0',
        ]));

        $response->assertSessionHasErrors('terms');
    }

    // -------------------------------------------------------------------------
    // Requirement 17.12: Input preserved on validation error (except password)
    // -------------------------------------------------------------------------

    /**
     * Requirement 17.12: Name and email are preserved as old input on error; password fields are not.
     */
    public function test_registration_preserves_input_values_on_error_except_password(): void
    {
        // Trigger a validation error by using a duplicate email
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->post(route('register.submit'), $this->validData([
            'name' => 'John Doe',
        ]));

        $response->assertSessionHasErrors('email');

        // Laravel flashes old input automatically for failed validation
        $response->assertSessionHas('_old_input');

        $oldInput = session('_old_input');
        $this->assertEquals('John Doe', $oldInput['name']);
        $this->assertEquals('john@example.com', $oldInput['email']);

        // Password fields should not be preserved in old input
        $this->assertArrayNotHasKey('password', $oldInput);
        $this->assertArrayNotHasKey('password_confirmation', $oldInput);
    }

    // -------------------------------------------------------------------------
    // Additional edge cases
    // -------------------------------------------------------------------------

    /**
     * Requirement 17.1-17.3: Name validation — missing name fails.
     */
    public function test_registration_without_name_fails(): void
    {
        $response = $this->post(route('register.submit'), $this->validData([
            'name' => '',
        ]));

        $response->assertSessionHasErrors('name');
    }

    /**
     * Requirement 17.2: Name shorter than 2 characters fails.
     */
    public function test_registration_with_name_too_short_fails(): void
    {
        $response = $this->post(route('register.submit'), $this->validData([
            'name' => 'A',
        ]));

        $response->assertSessionHasErrors('name');
    }

    /**
     * Requirement 17.4-17.5: Invalid email format fails.
     */
    public function test_registration_with_invalid_email_fails(): void
    {
        $response = $this->post(route('register.submit'), $this->validData([
            'email' => 'not-an-email',
        ]));

        $response->assertSessionHasErrors('email');
    }

    /**
     * Requirement 17: Registration form is accessible to guests.
     */
    public function test_registration_form_is_accessible_to_guests(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    /**
     * Requirement 13.4-13.5: Authenticated users are redirected away from the registration form.
     *
     * ValidateSession requires a database session record, which doesn't exist with the array
     * session driver used in tests. We skip it here since this test is focused on the guest
     * middleware redirect, not session validation.
     */
    public function test_authenticated_users_cannot_access_registration_form(): void
    {
        $user = User::factory()->create();

        $response = $this->withoutMiddleware(\App\Http\Middleware\ValidateSession::class)
            ->actingAs($user)
            ->get(route('register'));

        $response->assertRedirect(route('dashboard'));
    }
}
