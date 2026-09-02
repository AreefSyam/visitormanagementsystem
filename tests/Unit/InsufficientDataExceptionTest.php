<?php

namespace Tests\Unit;

use App\Exceptions\InsufficientDataException;
use Tests\TestCase;

class InsufficientDataExceptionTest extends TestCase
{
    /**
     * Test that forWeeklyTrend returns an exception with the correct message.
     */
    public function test_forWeeklyTrend_returns_exception_with_correct_message(): void
    {
        // Act
        $exception = InsufficientDataException::forWeeklyTrend();

        // Assert
        $this->assertInstanceOf(InsufficientDataException::class, $exception);
        $this->assertEquals('Weekly trends require at least 14 days of data.', $exception->getMessage());
    }

    /**
     * Test that forMonthlyTrend returns an exception with the correct message.
     */
    public function test_forMonthlyTrend_returns_exception_with_correct_message(): void
    {
        // Act
        $exception = InsufficientDataException::forMonthlyTrend();

        // Assert
        $this->assertInstanceOf(InsufficientDataException::class, $exception);
        $this->assertEquals('Monthly trends require at least 30 days of data.', $exception->getMessage());
    }

    /**
     * Test that the exception can be thrown and caught properly.
     */
    public function test_exception_can_be_thrown_and_caught(): void
    {
        // Arrange
        $this->expectException(InsufficientDataException::class);
        $this->expectExceptionMessage('Weekly trends require at least 14 days of data.');

        // Act
        throw InsufficientDataException::forWeeklyTrend();
    }
}
