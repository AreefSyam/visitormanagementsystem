<?php

namespace Tests\Unit;

use App\Models\Host;
use App\Models\Visit;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisitModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function scopeInPeriod_filters_visits_within_date_range(): void
    {
        // Arrange: Create visits on different dates
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Visit before the period
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subDays(10),
            'status' => 'checked_in',
        ]);

        // Visit within the period (should be included)
        $visitInPeriod1 = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subDays(5),
            'status' => 'checked_in',
        ]);

        // Another visit within the period (should be included)
        $visitInPeriod2 = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subDays(2),
            'status' => 'checked_out',
        ]);

        // Visit after the period
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->addDays(5),
            'status' => 'checked_in',
        ]);

        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now()->subDays(1);

        // Act: Apply the inPeriod scope
        $visitsInPeriod = Visit::inPeriod($startDate, $endDate)->get();

        // Assert: Only the two visits within the period should be returned
        $this->assertCount(2, $visitsInPeriod);
        $this->assertTrue($visitsInPeriod->contains('id', $visitInPeriod1->id));
        $this->assertTrue($visitsInPeriod->contains('id', $visitInPeriod2->id));
    }

    #[Test]
    public function scopeInPeriod_includes_visits_on_boundary_dates(): void
    {
        // Arrange: Create visits on exact start and end dates
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-01-31');

        // Visit at the start of the period (beginning of day)
        $visitAtStart = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => $startDate->copy()->startOfDay(),
            'status' => 'checked_in',
        ]);

        // Visit at the end of the period (end of day)
        $visitAtEnd = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => $endDate->copy()->endOfDay(),
            'status' => 'checked_in',
        ]);

        // Visit in the middle
        $visitInMiddle = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::parse('2024-01-15 12:00:00'),
            'status' => 'checked_out',
        ]);

        // Act: Apply the inPeriod scope
        $visitsInPeriod = Visit::inPeriod($startDate, $endDate)->get();

        // Assert: All three visits should be included
        $this->assertCount(3, $visitsInPeriod);
        $this->assertTrue($visitsInPeriod->contains('id', $visitAtStart->id));
        $this->assertTrue($visitsInPeriod->contains('id', $visitAtEnd->id));
        $this->assertTrue($visitsInPeriod->contains('id', $visitInMiddle->id));
    }

    #[Test]
    public function scopeInPeriod_returns_empty_when_no_visits_in_period(): void
    {
        // Arrange: Create visits outside the specified period
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subMonths(2),
            'status' => 'checked_out',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->addMonths(2),
            'status' => 'checked_in',
        ]);

        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        // Act: Apply the inPeriod scope
        $visitsInPeriod = Visit::inPeriod($startDate, $endDate)->get();

        // Assert: No visits should be returned
        $this->assertCount(0, $visitsInPeriod);
    }

    #[Test]
    public function scopeInPeriod_works_for_single_day_period(): void
    {
        // Arrange: Create visits on the same day
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $targetDate = Carbon::today();

        // Visit on the target day (morning)
        $visitMorning = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => $targetDate->copy()->setTime(9, 0, 0),
            'status' => 'checked_in',
        ]);

        // Visit on the target day (evening)
        $visitEvening = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => $targetDate->copy()->setTime(17, 30, 0),
            'status' => 'checked_out',
        ]);

        // Visit on a different day
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => $targetDate->copy()->addDay(),
            'status' => 'checked_in',
        ]);

        // Act: Apply the inPeriod scope for a single day
        $visitsInPeriod = Visit::inPeriod($targetDate, $targetDate)->get();

        // Assert: Only the two visits on that day should be returned
        $this->assertCount(2, $visitsInPeriod);
        $this->assertTrue($visitsInPeriod->contains('id', $visitMorning->id));
        $this->assertTrue($visitsInPeriod->contains('id', $visitEvening->id));
    }

    #[Test]
    public function scopeExcludingCancelled_filters_out_cancelled_visits(): void
    {
        // Arrange: Create visits with different statuses
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Checked-in visit (should be included)
        $checkedInVisit = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'status' => 'checked_in',
        ]);

        // Checked-out visit (should be included)
        $checkedOutVisit = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subHours(2),
            'check_out_at' => Carbon::now()->subHours(1),
            'status' => 'checked_out',
        ]);

        // Cancelled visit (should be excluded)
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'status' => 'cancelled',
        ]);

        // Act: Apply the excludingCancelled scope
        $nonCancelledVisits = Visit::excludingCancelled()->get();

        // Assert: Only non-cancelled visits should be returned
        $this->assertCount(2, $nonCancelledVisits);
        $this->assertTrue($nonCancelledVisits->contains('id', $checkedInVisit->id));
        $this->assertTrue($nonCancelledVisits->contains('id', $checkedOutVisit->id));
        $this->assertFalse($nonCancelledVisits->contains('status', 'cancelled'));
    }

    #[Test]
    public function scopeExcludingCancelled_returns_empty_when_all_visits_are_cancelled(): void
    {
        // Arrange: Create only cancelled visits
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'status' => 'cancelled',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subHour(),
            'status' => 'cancelled',
        ]);

        // Act: Apply the excludingCancelled scope
        $nonCancelledVisits = Visit::excludingCancelled()->get();

        // Assert: No visits should be returned
        $this->assertCount(0, $nonCancelledVisits);
    }

    #[Test]
    public function scopeExcludingCancelled_can_be_combined_with_other_scopes(): void
    {
        // Arrange: Create visits with different statuses and dates
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        // Visit in period, checked in (should be included)
        $visitInPeriod = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subDays(3),
            'status' => 'checked_in',
        ]);

        // Visit in period, but cancelled (should be excluded)
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subDays(2),
            'status' => 'cancelled',
        ]);

        // Visit outside period, checked in (should be excluded)
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subDays(10),
            'status' => 'checked_in',
        ]);

        // Act: Apply both scopes
        $filteredVisits = Visit::inPeriod($startDate, $endDate)
            ->excludingCancelled()
            ->get();

        // Assert: Only the visit that is in period and not cancelled should be returned
        $this->assertCount(1, $filteredVisits);
        $this->assertTrue($filteredVisits->contains('id', $visitInPeriod->id));
    }

    #[Test]
    public function scopeCompleted_returns_only_checked_out_visits_with_check_out_timestamp(): void
    {
        // Arrange: Create visits with different statuses
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        // Completed visit (checked out with timestamp) - should be included
        $completedVisit = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subHours(3),
            'check_out_at' => Carbon::now()->subHours(1),
            'status' => 'checked_out',
        ]);

        // Checked-in visit (no check-out) - should be excluded
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'check_out_at' => null,
            'status' => 'checked_in',
        ]);

        // Cancelled visit - should be excluded
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'check_out_at' => null,
            'status' => 'cancelled',
        ]);

        // Act: Apply the completed scope
        $completedVisits = Visit::completed()->get();

        // Assert: Only the completed visit should be returned
        $this->assertCount(1, $completedVisits);
        $this->assertTrue($completedVisits->contains('id', $completedVisit->id));
        $this->assertEquals('checked_out', $completedVisits->first()->status);
        $this->assertNotNull($completedVisits->first()->check_out_at);
    }

    #[Test]
    public function scopeCompleted_excludes_visits_with_checked_out_status_but_null_check_out_timestamp(): void
    {
        // Arrange: Create a visit with status 'checked_out' but null check_out_at (data anomaly)
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subHours(2),
            'check_out_at' => null, // Null check-out timestamp
            'status' => 'checked_out', // But status is checked_out
        ]);

        // Create a proper completed visit
        $properCompletedVisit = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subHours(3),
            'check_out_at' => Carbon::now()->subHours(1),
            'status' => 'checked_out',
        ]);

        // Act: Apply the completed scope
        $completedVisits = Visit::completed()->get();

        // Assert: Only the visit with both status and timestamp should be returned
        $this->assertCount(1, $completedVisits);
        $this->assertTrue($completedVisits->contains('id', $properCompletedVisit->id));
    }

    #[Test]
    public function scopeCompleted_returns_empty_when_no_completed_visits_exist(): void
    {
        // Arrange: Create only active visits
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now(),
            'check_out_at' => null,
            'status' => 'checked_in',
        ]);

        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subHour(),
            'check_out_at' => null,
            'status' => 'checked_in',
        ]);

        // Act: Apply the completed scope
        $completedVisits = Visit::completed()->get();

        // Assert: No visits should be returned
        $this->assertCount(0, $completedVisits);
    }

    #[Test]
    public function scopeCompleted_can_be_combined_with_other_scopes(): void
    {
        // Arrange: Create visits with different statuses and dates
        $visitor = Visitor::factory()->create();
        $host = Host::factory()->create();

        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        // Completed visit in period (should be included)
        $completedInPeriod = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subDays(3),
            'check_out_at' => Carbon::now()->subDays(3)->addHours(2),
            'status' => 'checked_out',
        ]);

        // Completed visit outside period (should be excluded)
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subDays(10),
            'check_out_at' => Carbon::now()->subDays(10)->addHours(1),
            'status' => 'checked_out',
        ]);

        // Active visit in period (should be excluded)
        Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id' => $host->id,
            'check_in_at' => Carbon::now()->subDays(2),
            'check_out_at' => null,
            'status' => 'checked_in',
        ]);

        // Act: Apply both scopes
        $filteredVisits = Visit::inPeriod($startDate, $endDate)
            ->completed()
            ->get();

        // Assert: Only the completed visit in period should be returned
        $this->assertCount(1, $filteredVisits);
        $this->assertTrue($filteredVisits->contains('id', $completedInPeriod->id));
    }
}
