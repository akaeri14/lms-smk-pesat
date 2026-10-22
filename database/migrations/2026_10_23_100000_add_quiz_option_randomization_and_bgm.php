<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->boolean('randomize_options')->default(true)->after('due_at');
            $table->string('bgm_path')->nullable()->after('randomize_options');
        });

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->string('question_type')->default('multiple_choice_single')->after('question_text');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_questions', fn (Blueprint $table) => $table->dropColumn('question_type'));
        Schema::table('quizzes', fn (Blueprint $table) => $table->dropColumn(['randomize_options', 'bgm_path']));
    }
};
