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
        Schema::table('proposals', function (Blueprint $table) {
            $table->string('billing_type', 20)->default('hourly')->after('description');
            $table->decimal('fixed_value', 10, 2)->nullable()->after('hourly_rate');
            $table->decimal('hours', 8, 2)->nullable()->change();
            $table->decimal('hourly_rate', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('proposals')->whereNull('hours')->update(['hours' => 0]);
        DB::table('proposals')->whereNull('hourly_rate')->update(['hourly_rate' => 0]);

        Schema::table('proposals', function (Blueprint $table) {
            $table->decimal('hours', 8, 2)->nullable(false)->change();
            $table->decimal('hourly_rate', 10, 2)->nullable(false)->change();
            $table->dropColumn(['billing_type', 'fixed_value']);
        });
    }
};
