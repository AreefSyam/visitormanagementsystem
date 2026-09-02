<?php

namespace App\Exceptions;

use Exception;

class InsufficientDataException extends Exception
{
    /**
     * Create an exception for insufficient weekly trend data.
     *
     * @return self
     */
    public static function forWeeklyTrend(): self
    {
        return new self(__('analytics.insufficient_data_weekly'));
    }

    /**
     * Create an exception for insufficient monthly trend data.
     *
     * @return self
     */
    public static function forMonthlyTrend(): self
    {
        return new self(__('analytics.insufficient_data_monthly'));
    }
}
