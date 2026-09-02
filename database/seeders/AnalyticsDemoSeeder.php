<?php

namespace Database\Seeders;

use App\Models\Host;
use App\Models\Visit;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AnalyticsDemoSeeder extends Seeder
{
    /**
     * Visit purposes distributed to give realistic purpose breakdown in analytics.
     */
    protected array $purposes = [
        'Business Meeting'    => 25,
        'Client Visit'        => 20,
        'Job Interview'       => 15,
        'Vendor Presentation' => 10,
        'Document Delivery'   => 10,
        'Training Session'    => 8,
        'Contract Signing'    => 5,
        'Product Demo'        => 4,
        'Audit Visit'         => 2,
        'Maintenance Work'    => 1,
    ];

    /**
     * Weighted hour distribution — peaks at 9–11am and 2–4pm.
     * Keys = hour (0–23), values = relative weight.
     */
    protected array $hourWeights = [
        7  => 1,
        8  => 5,
        9  => 14,
        10 => 16,
        11 => 12,
        12 => 5,
        13 => 4,
        14 => 15,
        15 => 14,
        16 => 10,
        17 => 4,
    ];

    public function run(): void
    {
        $this->command->info('Seeding analytics demo data…');

        // Reuse existing hosts/visitors or create fresh ones
        $hosts    = Host::where('is_active', true)->get();
        $visitors = Visitor::all();

        if ($hosts->count() < 10) {
            $hosts = Host::factory(20)->create();
            $this->command->info('  Created 20 hosts');
        }

        if ($visitors->count() < 20) {
            $visitors = Visitor::factory(60)->create();
            $this->command->info('  Created 60 visitors');
        }

        $inserted = 0;

        // ----------------------------------------------------------------
        // 1. Past 3 months  — ~8–12 visits/day (Mon–Fri), fewer on weekends
        // ----------------------------------------------------------------
        $start = Carbon::now()->subMonths(3)->startOfDay();
        $end   = Carbon::now()->subDays(1)->endOfDay();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $isWeekend = $date->isWeekend();
            $count     = $isWeekend
                ? fake()->numberBetween(1, 4)
                : fake()->numberBetween(8, 14);

            $inserted += $this->createVisitsForDay($date, $count, $hosts, $visitors, 'checked_out');
        }

        // ----------------------------------------------------------------
        // 2. This week (Mon to yesterday) — slightly higher volume
        // ----------------------------------------------------------------
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $yesterday = Carbon::yesterday()->endOfDay();

        if ($weekStart->lt($yesterday)) {
            for ($date = $weekStart->copy(); $date->lte($yesterday); $date->addDay()) {
                $isWeekend = $date->isWeekend();
                $count     = $isWeekend
                    ? fake()->numberBetween(2, 5)
                    : fake()->numberBetween(10, 18);

                $inserted += $this->createVisitsForDay($date, $count, $hosts, $visitors, 'checked_out');
            }
        }

        // ----------------------------------------------------------------
        // 3. Today — mix of checked_out, checked_in (active), and a few cancelled
        // ----------------------------------------------------------------
        $today = Carbon::today();

        // Checked-out visits earlier today
        $inserted += $this->createVisitsForDay($today, 8, $hosts, $visitors, 'checked_out', Carbon::now()->subHours(2));

        // Currently active (checked in, no checkout yet)
        $inserted += $this->createVisitsForDay($today, 5, $hosts, $visitors, 'checked_in');

        // A couple of cancelled visits today
        $inserted += $this->createVisitsForDay($today, 2, $hosts, $visitors, 'cancelled');

        $this->command->info("  Inserted {$inserted} visits total.");
        $this->command->info('Analytics demo data seeded successfully.');
    }

    /**
     * Create $count visits on a given $date with the given status.
     *
     * @param  Carbon            $date
     * @param  int               $count
     * @param  \Illuminate\Support\Collection  $hosts
     * @param  \Illuminate\Support\Collection  $visitors
     * @param  string            $status    checked_out | checked_in | cancelled
     * @param  Carbon|null       $maxCheckIn  upper bound for check-in time (defaults to end of day)
     * @return int  number of records inserted
     */
    protected function createVisitsForDay(
        Carbon $date,
        int $count,
        $hosts,
        $visitors,
        string $status,
        ?Carbon $maxCheckIn = null
    ): int {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $checkInHour   = $this->weightedRandomHour();
            $checkInMinute = fake()->numberBetween(0, 59);

            $checkIn = $date->copy()
                ->setHour($checkInHour)
                ->setMinute($checkInMinute)
                ->setSecond(0);

            // Respect upper bound if provided
            if ($maxCheckIn && $checkIn->gt($maxCheckIn)) {
                $checkIn = $maxCheckIn->copy()->subMinutes(fake()->numberBetween(5, 60));
            }

            // Derive check-out time
            $checkOut = null;

            if ($status === 'checked_out') {
                $durationMinutes = fake()->numberBetween(15, 240);
                $checkOut        = $checkIn->copy()->addMinutes($durationMinutes);

                // Don't let checkout bleed past end of day
                $endOfDay = $date->copy()->setHour(18)->setMinute(30);
                if ($checkOut->gt($endOfDay)) {
                    $checkOut = $endOfDay->copy();
                }
            }

            $rows[] = [
                'visitor_id'   => $visitors->random()->id,
                'host_id'      => $hosts->random()->id,
                'purpose'      => $this->weightedRandomPurpose(),
                'check_in_at'  => $checkIn->toDateTimeString(),
                'check_out_at' => $checkOut?->toDateTimeString(),
                'status'       => $status,
                'badge_number' => fake()->optional(0.6)->bothify('VMS-####'),
                'notes'        => fake()->optional(0.2)->sentence(),
                'created_at'   => $checkIn->toDateTimeString(),
                'updated_at'   => ($checkOut ?? $checkIn)->toDateTimeString(),
            ];
        }

        // Bulk insert for performance
        DB::table('visits')->insert($rows);

        return count($rows);
    }

    /**
     * Pick a random hour biased toward peak office hours.
     */
    protected function weightedRandomHour(): int
    {
        $pool = [];

        foreach ($this->hourWeights as $hour => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $pool[] = $hour;
            }
        }

        return $pool[array_rand($pool)];
    }

    /**
     * Pick a random visit purpose weighted by the configured distribution.
     */
    protected function weightedRandomPurpose(): string
    {
        $pool = [];

        foreach ($this->purposes as $purpose => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $pool[] = $purpose;
            }
        }

        return $pool[array_rand($pool)];
    }
}
