<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('??##??')),
            'name' => 'Kelas '.fake()->randomElement(['X', 'XI', 'XII']).' '.fake()->randomLetter(),
            'subject' => fake()->randomElement(['Matematika', 'Bahasa Indonesia', 'Pemrograman Web']),
            'description' => fake()->optional()->sentence(),
            'teacher_id' => User::factory()->state(['role' => 'guru']),
            'academic_year' => '2026/2027',
            'status' => 'active',
        ];
    }
}
