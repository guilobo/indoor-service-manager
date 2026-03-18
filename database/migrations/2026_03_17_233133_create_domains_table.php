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
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain_name');
            $table->string('status', 20);
            $table->longText('notes')->nullable();
            $table->longText('credentials')->nullable();
            $table->string('ftp_host')->nullable();
            $table->string('ftp_user')->nullable();
            $table->text('ftp_password')->nullable();
            $table->string('hosting')->nullable();
            $table->string('panel_url')->nullable();
            $table->json('email_accounts')->nullable();
            $table->json('other_data')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['contract_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
