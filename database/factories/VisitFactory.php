<?php

namespace Database\Factories;

use App\Models\Host;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitFactory extends Factory
{
    protected static array $purposes = [
        'Business Meeting',
        'Job Interview',
        'Document Delivery',
        'Vendor Presentation',
        'Training Session',
        'Client Visit',
        'Contract Signing',
        'Product Demo',
        'Audit Visit',
        'Maintenance Work',
    ];

    public function definition(): array
    {
        $checkIn  = fake()->dateTimeBetween('-60 days', 'now');
        $hasCheckOut = fake()->boolean(75);
        $checkOut = $hasCheckOut
            ? fake()->dateTimeBetween($checkIn, (clone $checkIn)->modify('+4 hours'))
            : null;

        return [
            'visitor_id'   => Visitor::factory(),
            'host_id'      => Host::factory(),
            'purpose'      => fake()->randomElement(self::$purposes),
            'check_in_at'  => $checkIn,
            'check_out_at' => $checkOut,
            'status'       => $checkOut ? 'checked_out' : 'checked_in',
            'badge_number' => fake()->optional(0.5)->bothify('VMS-####'),
            'notes'        => fake()->optional(0.3)->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'check_out_at' => null,
            'status'       => 'checked_in',
            'check_in_at'  => fake()->dateTimeBetween('-3 hours', 'now'),
        ]);
    }

    public function today(): static
    {
        return $this->state(function () {
            $checkIn  = fake()->dateTimeBetween('today', 'now');
            $hasCheckOut = fake()->boolean(60);
            $checkOut = $hasCheckOut
                ? fake()->dateTimeBetween($checkIn, 'now')
                : null;

            return [
                'check_in_at'  => $checkIn,
                'check_out_at' => $checkOut,
                'status'       => $checkOut ? 'checked_out' : 'checked_in',
            ];
        });
    }
}
