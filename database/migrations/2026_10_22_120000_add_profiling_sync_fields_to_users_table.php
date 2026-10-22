<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('profiling_external_id')->nullable()->unique()->after('id');
            $table->timestamp('profiling_synced_at')->nullable()->after('status');
            $table->json('profiling_data')->nullable()->after('profiling_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['profiling_external_id']);
            $table->dropColumn(['profiling_external_id', 'profiling_synced_at', 'profiling_data']);
        });
    }
};
