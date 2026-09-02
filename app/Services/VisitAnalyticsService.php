<?php

namespace App\Services;

use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service class for visit analytics calculations.
 * 
 * Provides methods for calculating KPIs, trends, and breakdowns
 * from visit data with time period filtering.
 * 
 * DATA ACCURACY & INTEGRITY (Requirement 15.x / Task 18.1):
 * - All calculations are performed directly from database (15.1)
 * - Only visits with valid check_in_at timestamps are included (15.2)
 * - Cancelled visits are excluded from all calculations (15.3)
 * - Timezone-aware date bucketing via Carbon (15.4)
 * - Null values handled safely in all aggregations (15.5)
 * - Deterministic: same period produces identical results (15.6)
 * 
 * Requirements: 2.7, 11.5, 15.1-15.6
 */
class VisitAnalyticsService
{
    /**
     * Create a new VisitAnalyticsService instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get KPI metrics: total visits, active, completed, average duration
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array ['total_visits' => int, 'active_visits' => int,
     *                'completed_visits' => int, 'avg_duration' => ?string]
     */
    public function getKpiMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $baseQuery = Visit::query()
            ->inPeriod($startDate, $endDate)
            ->excludingCancelled();

        // Calculate total visits
        $totalVisits = $baseQuery->count();

        // Calculate active visits (checked_in status)
        $activeVisits = (clone $baseQuery)->where('status', 'checked_in')->count();

        // Calculate completed visits (checked_out status)
        $completedVisits = (clone $baseQuery)->where('status', 'checked_out')->count();

        // Calculate average duration from completed visits
        $avgDurationData = $this->getAverageDuration($startDate, $endDate);

        return [
            'total_visits' => $totalVisits,
            'active_visits' => $activeVisits,
            'completed_visits' => $completedVisits,
            'avg_duration' => $avgDurationData['formatted'],
            'avg_duration_formatted' => $avgDurationData['formatted'], // For PDF template compatibility
        ];
    }

    /**
     * Get daily visit counts grouped by date
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection [{date: '2026-01-15', count: 23}, ...]
     */
    public function getDailyTrend(Carbon $startDate, Carbon $endDate): Collection
    {
        $periodInDays = $startDate->diffInDays($endDate);

        // If period > 90 days, group by week instead
        if ($periodInDays > 90) {
            return $this->getDailyTrendGroupedByWeek($startDate, $endDate);
        }

        // Group by date
        $results = Visit::query()
            ->select(DB::raw('DATE(check_in_at) as date'), DB::raw('COUNT(*) as count'))
            ->inPeriod($startDate, $endDate)
            ->excludingCancelled()
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $results;
    }

    /**
     * Helper method to get daily trend grouped by week for periods > 90 days
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection
     */
    protected function getDailyTrendGroupedByWeek(Carbon $startDate, Carbon $endDate): Collection
    {
        // Get all visits and group them by week in PHP
        $visits = Visit::query()
            ->select('check_in_at')
            ->inPeriod($startDate, $endDate)
            ->excludingCancelled()
            ->get();

        // Group by ISO week start date (Monday)
        $weeklyGroups = $visits->groupBy(function ($visit) {
            $checkInDate = Carbon::parse($visit->check_in_at);
            // Get the Monday of the week
            return $checkInDate->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        });

        // Convert to collection format expected by the view
        $results = $weeklyGroups->map(function ($group, $weekStart) {
            return (object) [
                'date' => $weekStart,
                'count' => $group->count(),
            ];
        })->sortBy('date')->values();

        return $results;
    }

    /**
     * Get weekly visit counts grouped by ISO week start date
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection [{week_start: '2026-01-13', count: 87}, ...]
     * @throws \App\Exceptions\InsufficientDataException if period < 14 days
     */
    public function getWeeklyTrend(Carbon $startDate, Carbon $endDate): Collection
    {
        // Requirement 4.5: Throw exception if period < 14 days
        // Use floored difference to match whole days
        if ($startDate->copy()->startOfDay()->diffInDays($endDate->copy()->endOfDay(), false) < 14) {
            throw \App\Exceptions\InsufficientDataException::forWeeklyTrend();
        }

        // Requirement 11.3: Cache expensive trend queries for 5 minutes
        $cacheKey = "analytics.weekly_trend.{$startDate->format('Ymd')}.{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($startDate, $endDate) {
            // Requirements 4.1, 4.2, 4.4: Group visits by ISO week (Monday-Sunday)
            // Fetch all visits and group them in PHP for database compatibility
            $visits = Visit::query()
                ->inPeriod($startDate, $endDate)
                ->excludingCancelled()
                ->orderBy('check_in_at')
                ->get();

            // Group by ISO week start date (Monday)
            $weeklyData = $visits->groupBy(function ($visit) {
                // Get the Carbon instance for check_in_at
                $checkInDate = Carbon::parse($visit->check_in_at);

                // Find the Monday of this week (ISO week starts on Monday)
                return $checkInDate->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            })->map(function ($weekVisits, $weekStart) {
                // Requirement 4.3: Format data with week_start label and count
                return [
                    'week_start' => $weekStart,
                    'count' => $weekVisits->count(),
                ];
            })->sortBy('week_start')->values();

            return $weeklyData;
        });
    }

    /**
     * Get monthly visit counts grouped by month
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection [{month: '2026-01', label: 'Jan 2026', count: 234}, ...]
     * @throws \App\Exceptions\InsufficientDataException if period < 30 days
     */
    public function getMonthlyTrend(Carbon $startDate, Carbon $endDate): Collection
    {
        // Check if period is at least 30 days
        if ($startDate->diffInDays($endDate) < 30) {
            throw \App\Exceptions\InsufficientDataException::forMonthlyTrend();
        }

        // Requirement 11.3: Cache expensive trend queries for 5 minutes
        $cacheKey = "analytics.monthly_trend.{$startDate->format('Ymd')}.{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($startDate, $endDate) {
            // Use database-agnostic date formatting
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                // SQLite uses strftime
                $monthFormat = "strftime('%Y-%m', check_in_at)";
            } else {
                // MySQL/PostgreSQL use DATE_FORMAT
                $monthFormat = "DATE_FORMAT(check_in_at, '%Y-%m')";
            }

            $results = Visit::query()
                ->selectRaw("{$monthFormat} as month, COUNT(*) as count")
                ->inPeriod($startDate, $endDate)
                ->excludingCancelled()
                ->groupBy('month')
                ->orderBy('month', 'asc')
                ->get();

            // Transform results to include formatted label
            return $results->map(function ($item) {
                $monthDate = Carbon::createFromFormat('Y-m', $item->month);
                return [
                    'month' => $item->month,
                    'label' => $monthDate->format('M Y'),
                    'count' => $item->count,
                ];
            });
        });
    }

    /**
     * Get visit counts by hour (0-23) and identify peak hours
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array ['hourly_counts' => [0 => 5, 1 => 2, ..., 23 => 8],
     *                'peak_hours' => [14, 15, 10]]
     */
    public function getPeakHours(Carbon $startDate, Carbon $endDate): array
    {
        // Requirements 6.1, 6.2, 6.6: Group check-ins by hour (0-23) within selected period
        // Use database-agnostic hour extraction
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite uses strftime with %H for hour (00-23)
            $hourFormat = "CAST(strftime('%H', check_in_at) AS INTEGER)";
        } else {
            // MySQL/PostgreSQL use HOUR()
            $hourFormat = "HOUR(check_in_at)";
        }

        // Query visits grouped by hour
        $results = Visit::query()
            ->selectRaw("{$hourFormat} as hour, COUNT(*) as count")
            ->inPeriod($startDate, $endDate)
            ->excludingCancelled()
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Initialize hourly counts array with zeros for all 24 hours
        // Requirement 6.2: Create hourly buckets (0-23)
        $hourlyCounts = array_fill(0, 24, 0);

        // Fill in actual counts from query results
        foreach ($results as $result) {
            $hourlyCounts[(int) $result->hour] = (int) $result->count;
        }

        // Requirement 6.3: Identify top 3 hours with highest check-in counts
        // Create array with hour => count for sorting
        $hourlyCountsForSorting = $hourlyCounts;
        arsort($hourlyCountsForSorting); // Sort descending by count

        // Get top 3 peak hours
        $peakHours = array_keys(array_slice($hourlyCountsForSorting, 0, 3, true));

        // Requirement 6.4, 6.5: Return hourly counts and peak hours
        return [
            'hourly_counts' => $hourlyCounts,
            'peak_hours' => $peakHours,
        ];
    }

    /**
     * Calculate average duration from completed visits
     *
     * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array ['avg_minutes' => ?float, 'formatted' => ?string,
     *                'completed_count' => int]
     */
    public function getAverageDuration(Carbon $startDate, Carbon $endDate): array
    {
        // Requirement 7.1: Calculate AVG(TIMESTAMPDIFF(MINUTE, check_in_at, check_out_at))
        // Requirement 7.2: Only include completed visits with non-null check_out_at
        // Use database-agnostic duration calculation
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: Calculate minutes between timestamps using julianday
            $durationExpression = "(julianday(check_out_at) - julianday(check_in_at)) * 24 * 60";
        } else {
            // MySQL/PostgreSQL: Use TIMESTAMPDIFF
            $durationExpression = "TIMESTAMPDIFF(MINUTE, check_in_at, check_out_at)";
        }

        $result = Visit::query()
            ->selectRaw("AVG({$durationExpression}) as avg_minutes, COUNT(*) as completed_count")
            ->inPeriod($startDate, $endDate)
            ->excludingCancelled()
            ->where('status', 'checked_out')
            ->whereNotNull('check_out_at')
            ->first();

        $avgMinutes = $result->avg_minutes ? (float) $result->avg_minutes : null;
        $completedCount = (int) $result->completed_count;

        // Requirement 7.6: Return null when no completed visits
        if ($completedCount === 0 || $avgMinutes === null) {
            return [
                'avg_minutes' => null,
                'formatted' => null,
                'completed_count' => 0,
            ];
        }

        // Requirement 7.3: Format as hours and minutes
        // Round to nearest minute first to handle floating-point precision issues
        $totalMinutes = round($avgMinutes);
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        // Requirement 7.4: Return array with proper structure
        if ($hours > 0 && $minutes > 0) {
            $formatted = "{$hours}h {$minutes}min";
        } elseif ($hours > 0) {
            $formatted = "{$hours}h";
        } else {
            $formatted = "{$minutes}min";
        }

        return [
            'avg_minutes' => $avgMinutes,
            'formatted' => $formatted,
            'completed_count' => $completedCount,
        ];
    }

    /**
     * Get visit counts grouped by purpose (top 10)
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection [{purpose: 'Meeting', count: 45, percentage: 23.4}, ...]
     */
    public function getPurposeBreakdown(Carbon $startDate, Carbon $endDate): Collection
    {
        // First, get the total count of visits in the period (excluding cancelled)
        $totalVisits = Visit::query()
            ->inPeriod($startDate, $endDate)
            ->excludingCancelled()
            ->count();

        // Return empty collection if no visits
        if ($totalVisits === 0) {
            return collect([]);
        }

        // Query visits grouped by purpose with empty string handling
        // Use NULLIF to convert empty strings to NULL, then COALESCE to convert to "Unspecified"
        $results = Visit::query()
            ->selectRaw('COALESCE(NULLIF(purpose, ""), "Unspecified") as purpose, COUNT(*) as count')
            ->inPeriod($startDate, $endDate)
            ->excludingCancelled()
            ->groupBy(DB::raw('COALESCE(NULLIF(purpose, ""), "Unspecified")'))
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Calculate percentages and format data
        return $results->map(function ($item) use ($totalVisits) {
            return [
                'purpose' => $item->purpose,
                'count' => $item->count,
                'percentage' => round(($item->count / $totalVisits) * 100, 1),
            ];
        });
    }
}
