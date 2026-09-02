<?php

namespace Tests\Unit\Rules;

use App\Rules\PasswordComplexity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PasswordComplexity validation rule.
 * Validates: Requirement 18
 */
class PasswordComplexityTest extends TestCase
{
    private PasswordComplexity $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new PasswordComplexity();
    }

    #[Test]
    public function it_fails_when_password_has_no_uppercase_letter(): void
    {
        $result = $this->rule->passes('password', 'lowercase1!');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_fails_when_password_has_no_lowercase_letter(): void
    {
        $result = $this->rule->passes('password', 'UPPERCASE1!');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_fails_when_password_has_no_number(): void
    {
        $result = $this->rule->passes('password', 'NoNumber!');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_fails_when_password_has_no_special_character(): void
    {
        $result = $this->rule->passes('password', 'NoSpecial1');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_passes_when_password_meets_all_requirements(): void
    {
        $result = $this->rule->passes('password', 'Valid1Password!');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_passes_with_various_valid_special_characters(): void
    {
        $specialChars = ['!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '_', '=', '+'];

        foreach ($specialChars as $char) {
            $result = $this->rule->passes('password', "ValidPass1{$char}");
            $this->assertTrue($result, "Expected password with '{$char}' to pass validation");
        }
    }

    #[Test]
    public function it_returns_correct_error_message(): void
    {
        $message = $this->rule->message();

        $this->assertSame(
            'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            $message
        );
    }
}
