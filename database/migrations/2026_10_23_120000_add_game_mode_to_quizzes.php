<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->string('game_mode')->default('live_individual')->after('quiz_mode');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', fn (Blueprint $table) => $table->dropColumn('game_mode'));
    }
};
