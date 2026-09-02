<?php

namespace Database\Seeders;

use App\Models\Host;
use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create 20 hosts spread across departments
        $hosts = Host::factory(20)->create();

        // Create 40 visitors
        $visitors = Visitor::factory(40)->create();

        // Historical visits: past 60 days — checked out
        Visit::factory(80)
            ->recycle($visitors)
            ->recycle($hosts)
            ->create([
                'status' => 'checked_out',
            ]);

        // Today's visits — mix of checked in and checked out
        Visit::factory(8)
            ->today()
            ->recycle($visitors)
            ->recycle($hosts)
            ->create();

        // Active visitors currently on-site
        Visit::factory(5)
            ->active()
            ->recycle($visitors)
            ->recycle($hosts)
            ->create();

        // A handful of cancelled visits
        Visit::factory(5)
            ->recycle($visitors)
            ->recycle($hosts)
            ->create([
                'status'       => 'cancelled',
                'check_out_at' => null,
            ]);

        $this->command->info('Seeded: 20 hosts, 40 visitors, ~98 visits (5 active now)');
    }
}
