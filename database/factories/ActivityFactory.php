<?php

namespace Database\Factories;

use App\ActivityKanbanStatus;
use App\ActivityPriority;
use App\Models\Activity;
use App\Models\Contract;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $activityDate = fake()->dateTimeBetween('-2 months', 'now');

        return [
            'contract_id' => Contract::factory(),
            'proposal_id' => null,
            'domain_id' => null,
            'service_id' => Service::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'activity_date' => $activityDate,
            'duration_minutes' => fake()->numberBetween(15, 240),
            'reference_period' => $activityDate->format('Y-m'),
            'time_entries' => [
                [
                    'started_at' => $activityDate->format('Y-m-d 09:00:00'),
                    'ended_at' => $activityDate->format('Y-m-d 10:00:00'),
                ],
            ],
            'is_in_progress' => false,
            'images' => [],
            'files' => [],
            'external_links' => fake()->boolean(30) ? [fake()->url()] : [],
            'kanban_status' => ActivityKanbanStatus::Todo,
            'kanban_position' => fake()->numberBetween(1, 100),
            'priority' => ActivityPriority::Normal,
            'completed_at' => null,
            'show_on_task_board' => false,
        ];
    }
}
