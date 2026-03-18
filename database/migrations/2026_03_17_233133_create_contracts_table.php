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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->longText('description')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('monthly_hours', 8, 2)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('domain_rate', 10, 2)->nullable();
            $table->string('status', 20);
            $table->longText('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
