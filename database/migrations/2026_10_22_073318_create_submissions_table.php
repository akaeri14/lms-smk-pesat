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
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('file_path')->nullable();
            $table->text('submission_text')->nullable();
            $table->dateTime('submitted_at');
            $table->enum('status', ['submitted', 'late', 'graded'])->default('submitted');
            $table->unsignedInteger('score')->nullable();
            $table->text('teacher_feedback')->nullable();
            $table->dateTime('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
