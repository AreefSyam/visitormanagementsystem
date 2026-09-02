<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class HostFactory extends Factory
{
    protected static array $departments = [
        'Human Resources',
        'Finance',
        'Information Technology',
        'Operations',
        'Sales & Marketing',
        'Legal',
        'Customer Service',
        'Executive Office',
    ];

    protected static array $positions = [
        'Manager',
        'Senior Manager',
        'Director',
        'Executive',
        'Officer',
        'Coordinator',
        'Specialist',
        'Head of Department',
    ];

    public function definition(): array
    {
        return [
            'name'       => fake()->name(),
            'email'      => fake()->unique()->companyEmail(),
            'phone'      => fake()->optional(0.8)->numerify('03-########'),
            'department' => fake()->randomElement(self::$departments),
            'position'   => fake()->optional(0.8)->randomElement(self::$positions),
            'is_active'  => fake()->boolean(90),
        ];
    }
}
