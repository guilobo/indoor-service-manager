<?php

use App\Livewire\CurrentTaskNavigation;
use App\Models\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows the current task link and timer badge when there is a task in progress', function () {
    Activity::factory()->create([
        'is_in_progress' => true,
        'time_entries' => [
            [
                'started_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
                'ended_at' => null,
            ],
        ],
    ]);

    Livewire::test(CurrentTaskNavigation::class)
        ->assertSee('Tarefa em andamento')
        ->assertSee(':');
});
