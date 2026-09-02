<?php

namespace Tests\Unit;

use App\Models\Host;
use App\Models\Visit;
use App\Models\Visitor;
use App\Services\VisitAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisitAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private VisitAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VisitAnalyticsService();
    }

    #[Test]
    public function getKpiMetrics_returns_correct_counts(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create 2 checked-in visits
        Visit::factory()->count(2)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'status' => 'checked_in',
            'check_out_at' => null,
        ]);

        // Create 3 checked-out visits with durations
        Visit::factory()->count(3)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subHours(2),
            'check_out_at' => Carbon::now()->subHour(),
            'status' => 'checked_out',
        ]);

        // Act: Get KPI metrics
        $metrics = $this->service->getKpiMetrics($startDate, $endDate);

        // Assert: Verify counts
        $this->assertEquals(5, $metrics['total_visits']);
        $this->assertEquals(2, $metrics['active_visits']);
        $this->assertEquals(3, $metrics['completed_visits']);
        $this->assertNotNull($metrics['avg_duration']);
    }

    #[Test]
    public function getKpiMetrics_excludes_cancelled_visits(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create 1 checked-in visit
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'status' => 'checked_in',
        ]);

        // Create 1 checked-out visit
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subHours(2),
            'check_out_at' => Carbon::now()->subHour(),
            'status' => 'checked_out',
        ]);

        // Create 2 cancelled visits (should be excluded)
        Visit::factory()->count(2)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'status' => 'cancelled',
        ]);

        // Act: Get KPI metrics
        $metrics = $this->service->getKpiMetrics($startDate, $endDate);

        // Assert: Only non-cancelled visits should be counted
        $this->assertEquals(2, $metrics['total_visits']);
        $this->assertEquals(1, $metrics['active_visits']);
        $this->assertEquals(1, $metrics['completed_visits']);
    }

    #[Test]
    public function getKpiMetrics_calculates_average_duration_correctly(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create visit with 60-minute duration
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subHours(2),
            'check_out_at' => Carbon::now()->subHour(),
            'status' => 'checked_out',
        ]);

        // Create visit with 120-minute duration
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subHours(3),
            'check_out_at' => Carbon::now()->subHour(),
            'status' => 'checked_out',
        ]);

        // Act: Get KPI metrics
        $metrics = $this->service->getKpiMetrics($startDate, $endDate);

        // Assert: Average should be 90 minutes (1h 30min)
        $this->assertEquals(2, $metrics['completed_visits']);
        $this->assertNotNull($metrics['avg_duration']);
        $this->assertStringContainsString('h', $metrics['avg_duration']);
    }

    #[Test]
    public function getKpiMetrics_returns_null_duration_when_no_completed_visits(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create only active visits (no check-out)
        Visit::factory()->count(3)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'status' => 'checked_in',
            'check_out_at' => null,
        ]);

        // Act: Get KPI metrics
        $metrics = $this->service->getKpiMetrics($startDate, $endDate);

        // Assert: Duration should be null when no completed visits
        $this->assertEquals(3, $metrics['total_visits']);
        $this->assertEquals(3, $metrics['active_visits']);
        $this->assertEquals(0, $metrics['completed_visits']);
        $this->assertNull($metrics['avg_duration']);
    }

    #[Test]
    public function getKpiMetrics_returns_zeros_when_no_visits_in_period(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Create visits outside the period
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subDays(10),
            'status' => 'checked_in',
        ]);

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Act: Get KPI metrics
        $metrics = $this->service->getKpiMetrics($startDate, $endDate);

        // Assert: All counts should be zero
        $this->assertEquals(0, $metrics['total_visits']);
        $this->assertEquals(0, $metrics['active_visits']);
        $this->assertEquals(0, $metrics['completed_visits']);
        $this->assertNull($metrics['avg_duration']);
    }

    #[Test]
    public function getAverageDuration_formats_hours_and_minutes_correctly(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $checkInTime = Carbon::now()->subHours(2)->subMinutes(30);
        $checkOutTime = Carbon::now()->subHours(1);

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create visit with 90-minute duration (1h 30min)
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => $checkInTime,
            'check_out_at' => $checkOutTime,
            'status' => 'checked_out',
        ]);

        // Act: Get average duration
        $result = $this->service->getAverageDuration($startDate, $endDate);

        // Assert: Should format as hours and minutes
        $this->assertEqualsWithDelta(90, $result['avg_minutes'], 0.1);
        $this->assertEquals('1h 30min', $result['formatted']);
        $this->assertEquals(1, $result['completed_count']);
    }

    #[Test]
    public function getAverageDuration_formats_hours_only_when_no_remaining_minutes(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $checkInTime = Carbon::now()->subHours(3);
        $checkOutTime = Carbon::now()->subHour();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create visit with exactly 120-minute duration (2h)
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => $checkInTime,
            'check_out_at' => $checkOutTime,
            'status' => 'checked_out',
        ]);

        // Act: Get average duration
        $result = $this->service->getAverageDuration($startDate, $endDate);

        // Assert: Should format as hours only
        $this->assertEqualsWithDelta(120, $result['avg_minutes'], 0.1);
        $this->assertEquals('2h', $result['formatted']);
    }

    #[Test]
    public function getAverageDuration_formats_minutes_only_when_less_than_hour(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $checkInTime = Carbon::now()->subMinutes(50);
        $checkOutTime = Carbon::now()->subMinutes(5);

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create visit with 45-minute duration
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => $checkInTime,
            'check_out_at' => $checkOutTime,
            'status' => 'checked_out',
        ]);

        // Act: Get average duration
        $result = $this->service->getAverageDuration($startDate, $endDate);

        // Assert: Should format as minutes only
        $this->assertEqualsWithDelta(45, $result['avg_minutes'], 0.1);
        $this->assertEquals('45min', $result['formatted']);
    }

    #[Test]
    public function getAverageDuration_returns_null_when_no_completed_visits(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create only active visits
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'status' => 'checked_in',
            'check_out_at' => null,
        ]);

        // Act: Get average duration
        $result = $this->service->getAverageDuration($startDate, $endDate);

        // Assert: Should return null values
        $this->assertNull($result['avg_minutes']);
        $this->assertNull($result['formatted']);
        $this->assertEquals(0, $result['completed_count']);
    }

    #[Test]
    public function getAverageDuration_excludes_cancelled_visits(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $checkInTime = Carbon::now()->subHours(2);
        $checkOutTime = Carbon::now()->subHour();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create completed visit with 60-minute duration
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => $checkInTime,
            'check_out_at' => $checkOutTime,
            'status' => 'checked_out',
        ]);

        // Create cancelled visit with check_out_at (should be excluded)
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subHours(6),
            'check_out_at' => Carbon::now()->subHour(), // 300 minutes
            'status' => 'cancelled',
        ]);

        // Act: Get average duration
        $result = $this->service->getAverageDuration($startDate, $endDate);

        // Assert: Should only count the completed visit (60 minutes)
        $this->assertEqualsWithDelta(60, $result['avg_minutes'], 0.1);
        $this->assertEquals('1h', $result['formatted']);
        $this->assertEquals(1, $result['completed_count']);
    }

    #[Test]
    public function getWeeklyTrend_throws_exception_when_period_less_than_14_days(): void
    {
        // Arrange: Set period to 13 days (less than 14)
        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->addDays(13)->endOfDay();

        // Act & Assert: Should throw InsufficientDataException
        $this->expectException(\App\Exceptions\InsufficientDataException::class);
        $this->service->getWeeklyTrend($startDate, $endDate);
    }

    #[Test]
    public function getWeeklyTrend_groups_visits_by_iso_week(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Set date range to 3 weeks (21 days)
        $startDate = Carbon::parse('2026-01-05')->startOfDay(); // Monday
        $endDate = Carbon::parse('2026-01-25')->endOfDay(); // Sunday

        // Create visits in week 1 (Jan 5-11)
        Visit::factory()->count(5)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-07 10:00:00'), // Wednesday
            'status' => 'checked_in',
        ]);

        // Create visits in week 2 (Jan 12-18)
        Visit::factory()->count(8)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-14 14:00:00'), // Wednesday
            'status' => 'checked_in',
        ]);

        // Create visits in week 3 (Jan 19-25)
        Visit::factory()->count(3)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-21 09:00:00'), // Wednesday
            'status' => 'checked_in',
        ]);

        // Act: Get weekly trend
        $result = $this->service->getWeeklyTrend($startDate, $endDate);

        // Assert: Should have 3 weeks of data
        $this->assertCount(3, $result);

        // Verify structure
        $this->assertArrayHasKey('week_start', $result->first());
        $this->assertArrayHasKey('count', $result->first());

        // Verify counts
        $this->assertEquals(5, $result->first()['count']);
        $this->assertEquals(8, $result->get(1)['count']);
        $this->assertEquals(3, $result->last()['count']);
    }

    #[Test]
    public function getWeeklyTrend_starts_week_on_monday(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Set date range including a full week (14 days minimum)
        $startDate = Carbon::parse('2026-01-05')->startOfDay(); // Monday
        $endDate = Carbon::parse('2026-01-19')->endOfDay(); // Monday (15 days later)

        // Create visits on Tuesday of first week
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-06 10:00:00'), // Tuesday
            'status' => 'checked_in',
        ]);

        // Act: Get weekly trend
        $result = $this->service->getWeeklyTrend($startDate, $endDate);

        // Assert: Week should start on Monday (Jan 5)
        $firstWeekStart = Carbon::parse($result->first()['week_start']);
        $this->assertEquals(Carbon::MONDAY, $firstWeekStart->dayOfWeek);
        $this->assertEquals('2026-01-05', $firstWeekStart->toDateString());
    }

    #[Test]
    public function getWeeklyTrend_excludes_cancelled_visits(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::parse('2026-01-05')->startOfDay();
        $endDate = Carbon::parse('2026-01-19')->endOfDay(); // 15 days to meet minimum

        // Create 3 active visits
        Visit::factory()->count(3)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-07 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Create 2 cancelled visits (should be excluded)
        Visit::factory()->count(2)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-07 11:00:00'),
            'status' => 'cancelled',
        ]);

        // Act: Get weekly trend
        $result = $this->service->getWeeklyTrend($startDate, $endDate);

        // Assert: Should only count non-cancelled visits
        $this->assertEquals(3, $result->first()['count']);
    }

    #[Test]
    public function getWeeklyTrend_returns_empty_collection_when_no_visits(): void
    {
        // Arrange: No visits in period
        $startDate = Carbon::parse('2026-01-05')->startOfDay();
        $endDate = Carbon::parse('2026-01-25')->endOfDay();

        // Act: Get weekly trend
        $result = $this->service->getWeeklyTrend($startDate, $endDate);

        // Assert: Should return empty collection
        $this->assertCount(0, $result);
    }

    #[Test]
    public function getWeeklyTrend_allows_exactly_14_days(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Set period to exactly 14 days
        $startDate = Carbon::parse('2026-01-05')->startOfDay();
        $endDate = Carbon::parse('2026-01-19')->endOfDay(); // Exactly 14 days later

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-07 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Act: Should not throw exception
        $result = $this->service->getWeeklyTrend($startDate, $endDate);

        // Assert: Should return data successfully
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function getMonthlyTrend_throws_exception_when_period_less_than_30_days(): void
    {
        // Arrange: Set period to 29 days (less than 30)
        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->addDays(29)->endOfDay();

        // Act & Assert: Should throw InsufficientDataException
        $this->expectException(\App\Exceptions\InsufficientDataException::class);
        $this->service->getMonthlyTrend($startDate, $endDate);
    }

    #[Test]
    public function getMonthlyTrend_groups_visits_by_calendar_month(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Set date range spanning 3 months (Jan-Mar 2026)
        $startDate = Carbon::parse('2026-01-01')->startOfDay();
        $endDate = Carbon::parse('2026-03-31')->endOfDay();

        // Create visits in January
        Visit::factory()->count(10)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-15 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Create visits in February
        Visit::factory()->count(15)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-02-20 14:00:00'),
            'status' => 'checked_in',
        ]);

        // Create visits in March
        Visit::factory()->count(8)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-03-10 09:00:00'),
            'status' => 'checked_in',
        ]);

        // Act: Get monthly trend
        $result = $this->service->getMonthlyTrend($startDate, $endDate);

        // Assert: Should have 3 months of data
        $this->assertCount(3, $result);

        // Verify structure (month, label, count)
        $this->assertArrayHasKey('month', $result->first());
        $this->assertArrayHasKey('label', $result->first());
        $this->assertArrayHasKey('count', $result->first());

        // Verify month format (YYYY-MM)
        $this->assertEquals('2026-01', $result->first()['month']);
        $this->assertEquals('2026-02', $result->get(1)['month']);
        $this->assertEquals('2026-03', $result->last()['month']);

        // Verify counts
        $this->assertEquals(10, $result->first()['count']);
        $this->assertEquals(15, $result->get(1)['count']);
        $this->assertEquals(8, $result->last()['count']);
    }

    #[Test]
    public function getMonthlyTrend_formats_label_correctly(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Set date range spanning 2 months
        $startDate = Carbon::parse('2026-01-01')->startOfDay();
        $endDate = Carbon::parse('2026-02-28')->endOfDay();

        // Create visits in January
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-15 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Create visits in February
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-02-15 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Act: Get monthly trend
        $result = $this->service->getMonthlyTrend($startDate, $endDate);

        // Assert: Labels should be formatted as "Mon YYYY"
        $this->assertEquals('Jan 2026', $result->first()['label']);
        $this->assertEquals('Feb 2026', $result->last()['label']);
    }

    #[Test]
    public function getMonthlyTrend_orders_months_chronologically(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Set date range spanning multiple months
        $startDate = Carbon::parse('2026-01-01')->startOfDay();
        $endDate = Carbon::parse('2026-06-30')->endOfDay();

        // Create visits in non-chronological order
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-05-15 10:00:00'), // May
            'status' => 'checked_in',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-02-15 10:00:00'), // February
            'status' => 'checked_in',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-04-15 10:00:00'), // April
            'status' => 'checked_in',
        ]);

        // Act: Get monthly trend
        $result = $this->service->getMonthlyTrend($startDate, $endDate);

        // Assert: Months should be ordered chronologically
        $months = $result->pluck('month')->toArray();
        $this->assertEquals(['2026-02', '2026-04', '2026-05'], $months);
    }

    #[Test]
    public function getMonthlyTrend_excludes_cancelled_visits(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::parse('2026-01-01')->startOfDay();
        $endDate = Carbon::parse('2026-02-28')->endOfDay();

        // Create 5 active visits in January
        Visit::factory()->count(5)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-15 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Create 3 cancelled visits in January (should be excluded)
        Visit::factory()->count(3)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-16 11:00:00'),
            'status' => 'cancelled',
        ]);

        // Act: Get monthly trend
        $result = $this->service->getMonthlyTrend($startDate, $endDate);

        // Assert: Should only count non-cancelled visits
        $this->assertEquals(5, $result->first()['count']);
    }

    #[Test]
    public function getMonthlyTrend_returns_empty_collection_when_no_visits(): void
    {
        // Arrange: No visits in period
        $startDate = Carbon::parse('2026-01-01')->startOfDay();
        $endDate = Carbon::parse('2026-03-31')->endOfDay();

        // Act: Get monthly trend
        $result = $this->service->getMonthlyTrend($startDate, $endDate);

        // Assert: Should return empty collection
        $this->assertCount(0, $result);
    }

    #[Test]
    public function getMonthlyTrend_allows_exactly_30_days(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Set period to exactly 30 days
        $startDate = Carbon::parse('2026-01-01')->startOfDay();
        $endDate = Carbon::parse('2026-01-31')->endOfDay(); // Exactly 30 days later

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-15 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Act: Should not throw exception
        $result = $this->service->getMonthlyTrend($startDate, $endDate);

        // Assert: Should return data successfully
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function getMonthlyTrend_handles_visits_across_year_boundary(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Set date range spanning year boundary (Dec 2025 - Feb 2026)
        $startDate = Carbon::parse('2025-12-01')->startOfDay();
        $endDate = Carbon::parse('2026-02-28')->endOfDay();

        // Create visits in December 2025
        Visit::factory()->count(5)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2025-12-15 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Create visits in January 2026
        Visit::factory()->count(8)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-15 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Create visits in February 2026
        Visit::factory()->count(3)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-02-15 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Act: Get monthly trend
        $result = $this->service->getMonthlyTrend($startDate, $endDate);

        // Assert: Should handle year boundary correctly
        $this->assertCount(3, $result);
        $this->assertEquals('2025-12', $result->first()['month']);
        $this->assertEquals('Dec 2025', $result->first()['label']);
        $this->assertEquals(5, $result->first()['count']);

        $this->assertEquals('2026-01', $result->get(1)['month']);
        $this->assertEquals('Jan 2026', $result->get(1)['label']);
        $this->assertEquals(8, $result->get(1)['count']);
    }

    #[Test]
    public function getDailyTrend_groups_by_date(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::parse('2024-01-01')->startOfDay();
        $endDate = Carbon::parse('2024-01-03')->endOfDay();

        // Create visits on different dates
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 10:00:00'),
            'status' => 'checked_in',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 14:00:00'),
            'status' => 'checked_out',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-02 09:00:00'),
            'status' => 'checked_in',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-03 11:00:00'),
            'status' => 'checked_out',
        ]);

        // Act: Get daily trend
        $trend = $this->service->getDailyTrend($startDate, $endDate);

        // Assert: Should have 3 date groups
        $this->assertCount(3, $trend);
        $this->assertEquals('2024-01-01', $trend[0]->date);
        $this->assertEquals(2, $trend[0]->count);
        $this->assertEquals('2024-01-02', $trend[1]->date);
        $this->assertEquals(1, $trend[1]->count);
        $this->assertEquals('2024-01-03', $trend[2]->date);
        $this->assertEquals(1, $trend[2]->count);
    }

    #[Test]
    public function getDailyTrend_excludes_cancelled_visits(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::parse('2024-01-01')->startOfDay();
        $endDate = Carbon::parse('2024-01-01')->endOfDay();

        // Create 2 checked-in visits
        Visit::factory()->count(2)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Create 1 cancelled visit (should be excluded)
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 14:00:00'),
            'status' => 'cancelled',
        ]);

        // Act: Get daily trend
        $trend = $this->service->getDailyTrend($startDate, $endDate);

        // Assert: Should only count non-cancelled visits
        $this->assertCount(1, $trend);
        $this->assertEquals(2, $trend[0]->count);
    }

    #[Test]
    public function getDailyTrend_groups_by_week_when_period_exceeds_90_days(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Create a period > 90 days
        $startDate = Carbon::parse('2024-01-01')->startOfDay();
        $endDate = Carbon::parse('2024-05-01')->endOfDay(); // 121 days

        // Create visits on different weeks
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 10:00:00'), // Monday
            'status' => 'checked_in',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-03 14:00:00'), // Wednesday, same week
            'status' => 'checked_out',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-08 09:00:00'), // Next Monday
            'status' => 'checked_in',
        ]);

        // Act: Get daily trend (should group by week)
        $trend = $this->service->getDailyTrend($startDate, $endDate);

        // Assert: Should group by week
        $this->assertGreaterThan(0, $trend->count());
        // First week should have 2 visits
        $firstWeekCount = $trend->where('date', '2024-01-01')->first();
        $this->assertNotNull($firstWeekCount);
        $this->assertEquals(2, $firstWeekCount->count);
        // Second week should have 1 visit
        $secondWeekCount = $trend->where('date', '2024-01-08')->first();
        $this->assertNotNull($secondWeekCount);
        $this->assertEquals(1, $secondWeekCount->count);
    }

    #[Test]
    public function getDailyTrend_returns_empty_collection_when_no_visits(): void
    {
        // Arrange: No visits created
        $startDate = Carbon::parse('2024-01-01')->startOfDay();
        $endDate = Carbon::parse('2024-01-03')->endOfDay();

        // Act: Get daily trend
        $trend = $this->service->getDailyTrend($startDate, $endDate);

        // Assert: Should return empty collection
        $this->assertCount(0, $trend);
    }

    #[Test]
    public function getPurposeBreakdown_returns_empty_collection_when_no_visits(): void
    {
        // Arrange: No visits in period
        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Act: Get purpose breakdown
        $result = $this->service->getPurposeBreakdown($startDate, $endDate);

        // Assert: Should return empty collection
        $this->assertCount(0, $result);
    }

    #[Test]
    public function getPurposeBreakdown_groups_visits_by_purpose(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create visits with different purposes
        Visit::factory()->count(5)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Meeting',
            'status' => 'checked_in',
        ]);

        Visit::factory()->count(3)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Interview',
            'status' => 'checked_in',
        ]);

        Visit::factory()->count(2)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Delivery',
            'status' => 'checked_in',
        ]);

        // Act: Get purpose breakdown
        $result = $this->service->getPurposeBreakdown($startDate, $endDate);

        // Assert: Should have 3 purpose groups
        $this->assertCount(3, $result);

        // Verify structure
        $this->assertArrayHasKey('purpose', $result->first());
        $this->assertArrayHasKey('count', $result->first());
        $this->assertArrayHasKey('percentage', $result->first());
    }

    #[Test]
    public function getPurposeBreakdown_calculates_percentages_correctly(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create 6 visits with Meeting purpose (60%)
        Visit::factory()->count(6)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Meeting',
            'status' => 'checked_in',
        ]);

        // Create 4 visits with Interview purpose (40%)
        Visit::factory()->count(4)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Interview',
            'status' => 'checked_in',
        ]);

        // Act: Get purpose breakdown
        $result = $this->service->getPurposeBreakdown($startDate, $endDate);

        // Assert: Verify percentages
        $meetingData = $result->firstWhere('purpose', 'Meeting');
        $this->assertEquals(60.0, $meetingData['percentage']);
        $this->assertEquals(6, $meetingData['count']);

        $interviewData = $result->firstWhere('purpose', 'Interview');
        $this->assertEquals(40.0, $interviewData['percentage']);
        $this->assertEquals(4, $interviewData['count']);
    }

    #[Test]
    public function getPurposeBreakdown_handles_empty_purpose_as_unspecified(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create visits with empty purpose (should be treated as Unspecified)
        Visit::factory()->count(5)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => '',
            'status' => 'checked_in',
        ]);

        // Create visits with non-empty purpose
        Visit::factory()->count(5)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Meeting',
            'status' => 'checked_in',
        ]);

        // Act: Get purpose breakdown
        $result = $this->service->getPurposeBreakdown($startDate, $endDate);

        // Assert: Should have 2 groups (Unspecified and Meeting)
        $this->assertCount(2, $result);

        // Verify Unspecified group contains empty string purposes
        $unspecifiedData = $result->firstWhere('purpose', 'Unspecified');
        $this->assertNotNull($unspecifiedData);
        $this->assertEquals(5, $unspecifiedData['count']);

        // Verify percentages: 5 Unspecified (50%) and 5 Meeting (50%)
        $this->assertEquals(50.0, $unspecifiedData['percentage']);
    }

    #[Test]
    public function getPurposeBreakdown_sorts_by_count_descending(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create visits with different purposes in non-descending order
        Visit::factory()->count(2)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Delivery',
            'status' => 'checked_in',
        ]);

        Visit::factory()->count(8)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Meeting',
            'status' => 'checked_in',
        ]);

        Visit::factory()->count(5)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Interview',
            'status' => 'checked_in',
        ]);

        // Act: Get purpose breakdown
        $result = $this->service->getPurposeBreakdown($startDate, $endDate);

        // Assert: Should be sorted by count descending
        $this->assertEquals('Meeting', $result->first()['purpose']);
        $this->assertEquals(8, $result->first()['count']);

        $this->assertEquals('Interview', $result->get(1)['purpose']);
        $this->assertEquals(5, $result->get(1)['count']);

        $this->assertEquals('Delivery', $result->last()['purpose']);
        $this->assertEquals(2, $result->last()['count']);
    }

    #[Test]
    public function getPurposeBreakdown_limits_to_top_10_purposes(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create 15 unique purposes with different counts
        for ($i = 1; $i <= 15; $i++) {
            Visit::factory()->count($i)->create([
                'visitor_id' => $visitor->id,
                'host_id' => $host->id,
                'check_in_at' => Carbon::now(),
                'purpose' => "Purpose $i",
                'status' => 'checked_in',
            ]);
        }

        // Act: Get purpose breakdown
        $result = $this->service->getPurposeBreakdown($startDate, $endDate);

        // Assert: Should limit to top 10
        $this->assertCount(10, $result);

        // Verify it's the top 10 (should have Purpose 15 to Purpose 6)
        $this->assertEquals('Purpose 15', $result->first()['purpose']);
        $this->assertEquals('Purpose 6', $result->last()['purpose']);
    }

    #[Test]
    public function getPurposeBreakdown_excludes_cancelled_visits(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create 3 active visits with Meeting purpose
        Visit::factory()->count(3)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Meeting',
            'status' => 'checked_in',
        ]);

        // Create 2 cancelled visits with Meeting purpose (should be excluded)
        Visit::factory()->count(2)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Meeting',
            'status' => 'cancelled',
        ]);

        // Create 1 active visit with Interview purpose
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Interview',
            'status' => 'checked_in',
        ]);

        // Act: Get purpose breakdown
        $result = $this->service->getPurposeBreakdown($startDate, $endDate);

        // Assert: Should only count non-cancelled visits
        $this->assertCount(2, $result);

        $meetingData = $result->firstWhere('purpose', 'Meeting');
        $this->assertEquals(3, $meetingData['count']); // Not 5

        // Total should be 4 (3 Meeting + 1 Interview), not 6
        $totalVisits = $result->sum('count');
        $this->assertEquals(4, $totalVisits);
    }

    #[Test]
    public function getPurposeBreakdown_percentage_totals_100_percent(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Create visits with various purposes
        Visit::factory()->count(7)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Meeting',
            'status' => 'checked_in',
        ]);

        Visit::factory()->count(2)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Interview',
            'status' => 'checked_in',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'purpose' => 'Delivery',
            'status' => 'checked_in',
        ]);

        // Act: Get purpose breakdown
        $result = $this->service->getPurposeBreakdown($startDate, $endDate);

        // Assert: Total percentage should be 100% (accounting for rounding)
        $totalPercentage = $result->sum('percentage');
        $this->assertEquals(100.0, $totalPercentage);
    }

    #[Test]
    public function getPeakHours_returns_hourly_counts_array(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::parse('2024-01-01')->startOfDay();
        $endDate = Carbon::parse('2024-01-01')->endOfDay();

        // Create visits at different hours
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 09:00:00'), // Hour 9
            'status' => 'checked_in',
        ]);

        Visit::factory()->count(2)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 14:00:00'), // Hour 14
            'status' => 'checked_in',
        ]);

        Visit::factory()->count(3)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 10:30:00'), // Hour 10
            'status' => 'checked_in',
        ]);

        // Act: Get peak hours
        $result = $this->service->getPeakHours($startDate, $endDate);

        // Assert: Should have hourly_counts array with 24 elements
        $this->assertArrayHasKey('hourly_counts', $result);
        $this->assertCount(24, $result['hourly_counts']);

        // Verify specific hour counts
        $this->assertEquals(1, $result['hourly_counts'][9]);
        $this->assertEquals(3, $result['hourly_counts'][10]);
        $this->assertEquals(2, $result['hourly_counts'][14]);

        // Verify hours with no visits have 0 count
        $this->assertEquals(0, $result['hourly_counts'][0]);
        $this->assertEquals(0, $result['hourly_counts'][23]);
    }

    #[Test]
    public function getPeakHours_identifies_top_3_peak_hours(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::parse('2024-01-01')->startOfDay();
        $endDate = Carbon::parse('2024-01-01')->endOfDay();

        // Create visits with clear peak hours: 14 (8 visits), 10 (5 visits), 15 (3 visits)
        Visit::factory()->count(8)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 14:00:00'), // Hour 14 - highest
            'status' => 'checked_in',
        ]);

        Visit::factory()->count(5)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 10:00:00'), // Hour 10 - second
            'status' => 'checked_in',
        ]);

        Visit::factory()->count(3)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 15:00:00'), // Hour 15 - third
            'status' => 'checked_in',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 09:00:00'), // Hour 9 - not in top 3
            'status' => 'checked_in',
        ]);

        // Act: Get peak hours
        $result = $this->service->getPeakHours($startDate, $endDate);

        // Assert: Should have peak_hours array with top 3 hours
        $this->assertArrayHasKey('peak_hours', $result);
        $this->assertCount(3, $result['peak_hours']);

        // Verify the top 3 peak hours (in descending order by count)
        $this->assertContains(14, $result['peak_hours']); // 8 visits
        $this->assertContains(10, $result['peak_hours']); // 5 visits
        $this->assertContains(15, $result['peak_hours']); // 3 visits

        // Verify hour 9 is NOT in peak hours (only 1 visit)
        $this->assertNotContains(9, $result['peak_hours']);
    }

    #[Test]
    public function getPeakHours_excludes_cancelled_visits(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::parse('2024-01-01')->startOfDay();
        $endDate = Carbon::parse('2024-01-01')->endOfDay();

        // Create 5 active visits at hour 14
        Visit::factory()->count(5)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 14:00:00'),
            'status' => 'checked_in',
        ]);

        // Create 3 cancelled visits at hour 14 (should be excluded)
        Visit::factory()->count(3)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 14:30:00'),
            'status' => 'cancelled',
        ]);

        // Act: Get peak hours
        $result = $this->service->getPeakHours($startDate, $endDate);

        // Assert: Should only count non-cancelled visits
        $this->assertEquals(5, $result['hourly_counts'][14]); // Not 8
    }

    #[Test]
    public function getPeakHours_handles_multiple_visits_in_same_hour(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::parse('2024-01-01')->startOfDay();
        $endDate = Carbon::parse('2024-01-01')->endOfDay();

        // Create visits at different minutes within hour 14
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 14:00:00'),
            'status' => 'checked_in',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 14:15:00'),
            'status' => 'checked_in',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 14:45:00'),
            'status' => 'checked_in',
        ]);

        // Act: Get peak hours
        $result = $this->service->getPeakHours($startDate, $endDate);

        // Assert: All visits in hour 14 should be counted together
        $this->assertEquals(3, $result['hourly_counts'][14]);
    }

    #[Test]
    public function getPeakHours_returns_empty_peak_hours_when_no_visits(): void
    {
        // Arrange: No visits in period
        $startDate = Carbon::parse('2024-01-01')->startOfDay();
        $endDate = Carbon::parse('2024-01-01')->endOfDay();

        // Act: Get peak hours
        $result = $this->service->getPeakHours($startDate, $endDate);

        // Assert: Should have all zeros in hourly_counts
        $this->assertArrayHasKey('hourly_counts', $result);
        $this->assertCount(24, $result['hourly_counts']);
        $this->assertEquals(0, array_sum($result['hourly_counts']));

        // Peak hours should still return 3 hours (first 3 hours with 0 counts)
        $this->assertArrayHasKey('peak_hours', $result);
        $this->assertCount(3, $result['peak_hours']);
    }

    #[Test]
    public function getPeakHours_handles_visits_across_multiple_days(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Set period to 3 days
        $startDate = Carbon::parse('2024-01-01')->startOfDay();
        $endDate = Carbon::parse('2024-01-03')->endOfDay();

        // Create visits at hour 10 on different days
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 10:00:00'),
            'status' => 'checked_in',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-02 10:30:00'),
            'status' => 'checked_in',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-03 10:45:00'),
            'status' => 'checked_in',
        ]);

        // Act: Get peak hours
        $result = $this->service->getPeakHours($startDate, $endDate);

        // Assert: Should aggregate all hour 10 visits across all days
        $this->assertEquals(3, $result['hourly_counts'][10]);
    }

    #[Test]
    public function getPeakHours_handles_early_morning_and_late_night_hours(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::parse('2024-01-01')->startOfDay();
        $endDate = Carbon::parse('2024-01-01')->endOfDay();

        // Create visits at hour 0 (midnight) and hour 23 (11 PM)
        Visit::factory()->count(2)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 00:30:00'), // Hour 0
            'status' => 'checked_in',
        ]);

        Visit::factory()->count(3)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-01 23:45:00'), // Hour 23
            'status' => 'checked_in',
        ]);

        // Act: Get peak hours
        $result = $this->service->getPeakHours($startDate, $endDate);

        // Assert: Should correctly count boundary hours
        $this->assertEquals(2, $result['hourly_counts'][0]);
        $this->assertEquals(3, $result['hourly_counts'][23]);
    }

    #[Test]
    public function getWeeklyTrend_caches_results(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::parse('2026-01-05')->startOfDay();
        $endDate = Carbon::parse('2026-01-25')->endOfDay();

        // Create initial visits
        Visit::factory()->count(5)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-07 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Act: Get weekly trend first time (should cache)
        $result1 = $this->service->getWeeklyTrend($startDate, $endDate);

        // Create more visits after caching
        Visit::factory()->count(3)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-07 14:00:00'),
            'status' => 'checked_in',
        ]);

        // Act: Get weekly trend second time (should use cached data)
        $result2 = $this->service->getWeeklyTrend($startDate, $endDate);

        // Assert: Results should be the same (cached, so new visits not counted)
        $this->assertEquals($result1, $result2);
        $this->assertEquals(5, $result1->first()['count']);
        $this->assertEquals(5, $result2->first()['count']); // Still 5, not 8
    }

    #[Test]
    public function getMonthlyTrend_caches_results(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::parse('2026-01-01')->startOfDay();
        $endDate = Carbon::parse('2026-03-31')->endOfDay();

        // Create initial visits
        Visit::factory()->count(10)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-15 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Act: Get monthly trend first time (should cache)
        $result1 = $this->service->getMonthlyTrend($startDate, $endDate);

        // Create more visits after caching
        Visit::factory()->count(5)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-20 14:00:00'),
            'status' => 'checked_in',
        ]);

        // Act: Get monthly trend second time (should use cached data)
        $result2 = $this->service->getMonthlyTrend($startDate, $endDate);

        // Assert: Results should be the same (cached, so new visits not counted)
        $this->assertEquals($result1, $result2);
        $this->assertEquals(10, $result1->first()['count']);
        $this->assertEquals(10, $result2->first()['count']); // Still 10, not 15
    }

    #[Test]
    public function getWeeklyTrend_uses_different_cache_keys_for_different_periods(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Period 1: Jan 5-25
        $startDate1 = Carbon::parse('2026-01-05')->startOfDay();
        $endDate1 = Carbon::parse('2026-01-25')->endOfDay();

        // Period 2: Feb 1-21
        $startDate2 = Carbon::parse('2026-02-01')->startOfDay();
        $endDate2 = Carbon::parse('2026-02-21')->endOfDay();

        // Create visits in both periods
        Visit::factory()->count(5)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-07 10:00:00'),
            'status' => 'checked_in',
        ]);

        Visit::factory()->count(8)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-02-05 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Act: Get trends for both periods
        $result1 = $this->service->getWeeklyTrend($startDate1, $endDate1);
        $result2 = $this->service->getWeeklyTrend($startDate2, $endDate2);

        // Assert: Results should be different (different cache keys)
        $this->assertNotEquals($result1, $result2);
        $this->assertEquals(5, $result1->first()['count']);
        $this->assertEquals(8, $result2->first()['count']);
    }

    #[Test]
    public function getMonthlyTrend_uses_different_cache_keys_for_different_periods(): void
    {
        // Arrange: Create visitor and host
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Period 1: Jan-Mar 2026
        $startDate1 = Carbon::parse('2026-01-01')->startOfDay();
        $endDate1 = Carbon::parse('2026-03-31')->endOfDay();

        // Period 2: Apr-Jun 2026
        $startDate2 = Carbon::parse('2026-04-01')->startOfDay();
        $endDate2 = Carbon::parse('2026-06-30')->endOfDay();

        // Create visits in both periods
        Visit::factory()->count(10)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-01-15 10:00:00'),
            'status' => 'checked_in',
        ]);

        Visit::factory()->count(15)->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2026-04-15 10:00:00'),
            'status' => 'checked_in',
        ]);

        // Act: Get trends for both periods
        $result1 = $this->service->getMonthlyTrend($startDate1, $endDate1);
        $result2 = $this->service->getMonthlyTrend($startDate2, $endDate2);

        // Assert: Results should be different (different cache keys)
        $this->assertNotEquals($result1, $result2);
        $this->assertEquals(10, $result1->first()['count']);
        $this->assertEquals(15, $result2->first()['count']);
    }
}
