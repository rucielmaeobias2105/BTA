<?php

namespace Database\Factories;

use App\Models\Technician;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Technician>
 */
class TechnicianFactory extends Factory
{
    protected $model = Technician::class;

    public function definition(): array
    {
        $name = $this->faker->name();

        return [
            'name' => $name,
            // No photo: the list and the booking picker both have to render the
            // initials stand-in, so a generated row exercises that path.
            'photo_path' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function name(string $name): static
    {
        return $this->state(fn (array $attributes) => ['name' => $name]);
    }

    public function photo(): static
    {
        return $this->state(fn (array $attributes) => [
            'photo_path' => 'technician-photos/'.Str::random(40).'.jpg',
        ]);
    }
}
