<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_a_quiz_with_questions_and_options(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);

        $response = $this->actingAs($teacher)->post(route('classes.quizzes.store', $schoolClass), [
            'title' => 'Kuis HTML Dasar',
            'description' => 'Uji pemahaman HTML & CSS',
            'duration_minutes' => 10,
            'questions' => [
                [
                    'question_text' => 'Tag HTML untuk membuat tautan / link adalah?',
                    'points' => 10,
                    'options' => [
                        ['option_text' => '<a>', 'is_correct' => true],
                        ['option_text' => '<link>', 'is_correct' => false],
                        ['option_text' => '<href>', 'is_correct' => false],
                        ['option_text' => '<url>', 'is_correct' => false],
                    ],
                ],
                [
                    'question_text' => 'Tag untuk heading tingkat pertama adalah?',
                    'points' => 10,
                    'options' => [
                        ['option_text' => '<h6>', 'is_correct' => false],
                        ['option_text' => '<h1>', 'is_correct' => true],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('classes.show', $schoolClass->id));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('quizzes', [
            'class_id' => $schoolClass->id,
            'title' => 'Kuis HTML Dasar',
            'duration_minutes' => 10,
            'is_published' => 1,
        ]);

        $quiz = Quiz::where('title', 'Kuis HTML Dasar')->first();
        $this->assertEquals(2, $quiz->questions()->count());
        $this->assertEquals(6, $quiz->questions()->withCount('options')->get()->sum('options_count'));
    }

    public function test_student_cannot_create_a_quiz(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $schoolClass = SchoolClass::factory()->create();

        $response = $this->actingAs($student)->post(route('classes.quizzes.store', $schoolClass), [
            'title' => 'Kuis Palsu',
            'duration_minutes' => 10,
            'questions' => [
                [
                    'question_text' => 'Soal 1',
                    'points' => 10,
                    'options' => [
                        ['option_text' => 'A', 'is_correct' => true],
                        ['option_text' => 'B', 'is_correct' => false],
                    ],
                ],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_enrolled_student_can_play_and_submit_quiz_attempt(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $student = User::factory()->create(['role' => 'siswa']);
        $schoolClass->students()->attach($student, ['joined_at' => now()]);

        $quiz = Quiz::create([
            'class_id' => $schoolClass->id,
            'title' => 'Kuis Praktik',
            'duration_minutes' => 15,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $teacher->id,
        ]);

        $q1 = $quiz->questions()->create(['question_text' => '1 + 1 = ?', 'points' => 10]);
        $q1_opt1 = $q1->options()->create(['option_text' => '2', 'is_correct' => true]);
        $q1_opt2 = $q1->options()->create(['option_text' => '3', 'is_correct' => false]);

        $q2 = $quiz->questions()->create(['question_text' => 'Ibu kota Indonesia?', 'points' => 10]);
        $q2_opt1 = $q2->options()->create(['option_text' => 'Jakarta/IKN', 'is_correct' => true]);
        $q2_opt2 = $q2->options()->create(['option_text' => 'Bandung', 'is_correct' => false]);

        // Student visits play page
        $this->actingAs($student)->get(route('quizzes.play', $quiz))->assertOk();

        // Student submits answers: q1 correct, q2 wrong
        $response = $this->actingAs($student)->post(route('quizzes.attempts.store', $quiz), [
            'answers' => [
                $q1->id => $q1_opt1->id, // Benar
                $q2->id => $q2_opt2->id, // Salah
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 1 dari 2 benar = 50%
        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'score' => 50,
            'total_correct' => 1,
            'total_questions' => 2,
        ]);
    }

    public function test_unenrolled_student_cannot_attempt_quiz(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $otherStudent = User::factory()->create(['role' => 'siswa']); // Tidak terdaftar

        $quiz = Quiz::create([
            'class_id' => $schoolClass->id,
            'title' => 'Kuis Rahasia',
            'duration_minutes' => 15,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $teacher->id,
        ]);

        $response = $this->actingAs($otherStudent)->post(route('quizzes.attempts.store', $quiz), [
            'answers' => [],
        ]);

        $response->assertForbidden();
    }
}
