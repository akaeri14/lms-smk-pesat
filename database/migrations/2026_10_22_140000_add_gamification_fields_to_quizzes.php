<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('time_per_question')->default(30)->after('duration_minutes');
            $table->boolean('enable_fifty_fifty')->default(true)->after('time_per_question');
            $table->boolean('enable_freeze_time')->default(true)->after('enable_fifty_fifty');
        });

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('time_limit_seconds')->nullable()->after('points');
        });

        Schema::table('quiz_attempts', function (Blueprint $table): void {
            $table->unsignedInteger('dynamic_score')->default(0)->after('score');
            $table->unsignedInteger('time_taken_seconds')->nullable()->after('total_questions');
            $table->json('answer_times')->nullable()->after('answers');
            $table->json('powerups_used')->nullable()->after('answer_times');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table): void {
            $table->dropColumn(['dynamic_score', 'time_taken_seconds', 'answer_times', 'powerups_used']);
        });

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->dropColumn('time_limit_seconds');
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            $table->dropColumn(['time_per_question', 'enable_fifty_fifty', 'enable_freeze_time']);
        });
    }
};
