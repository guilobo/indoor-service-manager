<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('kanban_status', 32)->default('todo')->index();
            $table->unsignedInteger('kanban_position')->default(0);
            $table->string('priority', 32)->default('normal')->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->boolean('show_on_task_board')->default(false)->index();

            $table->index(['show_on_task_board', 'kanban_status', 'kanban_position'], 'activities_task_board_index');
        });

        DB::table('activities')->update([
            'kanban_position' => DB::raw('id'),
        ]);

        DB::table('activities')
            ->where('is_in_progress', true)
            ->update([
                'kanban_status' => 'in_progress',
                'show_on_task_board' => true,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('activities_task_board_index');
            $table->dropColumn([
                'kanban_status',
                'kanban_position',
                'priority',
                'completed_at',
                'show_on_task_board',
            ]);
        });
    }
};
