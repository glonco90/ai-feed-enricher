<?php

namespace Database\Factories;

use App\Models\Feed;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feed>
 */
class FeedFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'feed-'.fake()->word(),
            'source_filename' => fake()->word().'.xml',
        ];
    }
}
