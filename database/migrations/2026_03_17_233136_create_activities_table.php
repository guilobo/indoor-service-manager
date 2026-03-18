<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->date('activity_date');
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->string('reference_period', 20)->nullable();
            $table->json('time_entries')->nullable();
            $table->boolean('is_in_progress')->default(false);
            $table->json('images')->nullable();
            $table->json('files')->nullable();
            $table->json('external_links')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'activity_date']);
            $table->index(['service_id', 'activity_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
