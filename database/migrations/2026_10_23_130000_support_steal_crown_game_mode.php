<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // game_mode menggunakan string agar kompatibel dengan SQLite, MySQL, dan PostgreSQL.
        // Nilai resmi yang didukung aplikasi: live_individual, assign, team_battle, steal_crown.
        DB::table('quizzes')->whereNull('game_mode')->update(['game_mode' => 'live_individual']);
    }

    public function down(): void
    {
        DB::table('quizzes')->where('game_mode', 'steal_crown')->update(['game_mode' => 'live_individual']);
    }
};
