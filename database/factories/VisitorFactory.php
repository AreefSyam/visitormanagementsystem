<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class VisitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'      => fake()->name(),
            'email'     => fake()->optional(0.7)->safeEmail(),
            'phone'     => fake()->numerify('01#-########'),
            'company'   => fake()->optional(0.6)->company(),
            'id_type'   => fake()->randomElement(['ic', 'passport', 'driving_license']),
            'id_number' => fake()->numerify('######-##-####'),
            'photo'     => null,
        ];
    }
}
