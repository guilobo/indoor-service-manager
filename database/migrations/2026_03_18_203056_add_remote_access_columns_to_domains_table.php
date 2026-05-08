<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('access_type', 20)->default('ftp')->after('credentials');
            $table->unsignedSmallInteger('access_port')->nullable()->after('access_type');
            $table->string('access_root_path')->nullable()->after('access_port');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn([
                'access_type',
                'access_port',
                'access_root_path',
            ]);
        });
    }
};
