<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->string('quiz_mode')->default('live')->after('max_attempts');
            $table->dateTime('due_at')->nullable()->after('quiz_mode');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->dropColumn(['quiz_mode', 'due_at']);
        });
    }
};
