<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'max_score' => 100,
            'deadline' => now()->addWeek(),
            'created_by' => User::factory()->state(['role' => 'guru']),
        ];
    }
}
