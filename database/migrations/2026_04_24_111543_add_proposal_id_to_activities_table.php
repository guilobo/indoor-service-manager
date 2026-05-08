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
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('contract_id')->nullable()->change();
            $table->foreignId('proposal_id')->nullable()->after('contract_id')->constrained()->cascadeOnDelete();

            $table->index(['proposal_id', 'activity_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['proposal_id', 'activity_date']);
            $table->dropConstrainedForeignId('proposal_id');
            $table->foreignId('contract_id')->nullable(false)->change();
        });
    }
};
