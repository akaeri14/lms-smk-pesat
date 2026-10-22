<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssignmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_an_assignment_with_an_attachment(): void
    {
        Storage::fake('public');
        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);

        $this->actingAs($teacher)->post(route('classes.assignments.store', $schoolClass), [
            'title' => 'Praktik HTML',
            'description' => 'Buat halaman profil sederhana.',
            'deadline' => now()->addDay()->toDateTimeString(),
            'max_score' => 100,
            'attachment' => UploadedFile::fake()->create('instruksi.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseHas('assignments', ['class_id' => $schoolClass->id, 'title' => 'Praktik HTML']);
        Storage::disk('public')->assertExists(Assignment::query()->sole()->file_path);
    }

    public function test_student_cannot_create_an_assignment(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $schoolClass = SchoolClass::factory()->create();

        $this->actingAs($student)->post(route('classes.assignments.store', $schoolClass), [
            'title' => 'Tidak Diizinkan',
            'description' => 'Tes',
            'deadline' => now()->addDay()->toDateTimeString(),
            'max_score' => 100,
        ])->assertForbidden();
    }

    public function test_teacher_cannot_upload_attachment_larger_than_10mb(): void
    {
        Storage::fake('public');
        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);

        // 10241 KB = lebih dari 10MB (10240 KB)
        $response = $this->actingAs($teacher)->post(route('classes.assignments.store', $schoolClass), [
            'title' => 'Tugas File Besar',
            'description' => 'Instruksi',
            'deadline' => now()->addDay()->toDateTimeString(),
            'max_score' => 100,
            'attachment' => UploadedFile::fake()->create('modul-raksasa.pdf', 10241, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors(['attachment']);
        $this->assertDatabaseMissing('assignments', ['title' => 'Tugas File Besar']);
    }

    public function test_teacher_can_view_assignment_submission_counts(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $student1 = User::factory()->create(['role' => 'siswa']);
        $student2 = User::factory()->create(['role' => 'siswa']);
        $schoolClass->students()->attach([$student1->id, $student2->id], ['joined_at' => now()]);

        $assignment = Assignment::factory()->create([
            'class_id' => $schoolClass->id,
            'created_by' => $teacher->id,
        ]);

        // Student 1 mengumpulkan tugas
        $assignment->submissions()->create([
            'student_id' => $student1->id,
            'file_path' => 'submissions/tugas1.pdf',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($teacher)->get(route('assignments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Assignments/Index')
            ->has('assignments', 1)
            ->where('assignments.0.submissions_count', 1)
            ->where('assignments.0.school_class.students_count', 2)
        );
    }

    public function test_student_can_view_own_submission_status_on_assignment_list(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $student = User::factory()->create(['role' => 'siswa']);
        $schoolClass->students()->attach($student, ['joined_at' => now()]);

        $assignment = Assignment::factory()->create([
            'class_id' => $schoolClass->id,
            'created_by' => $teacher->id,
            'max_score' => 100,
        ]);

        $assignment->submissions()->create([
            'student_id' => $student->id,
            'file_path' => 'submissions/tugas-saya.pdf',
            'submitted_at' => now(),
            'status' => 'graded',
            'score' => 90,
            'teacher_feedback' => 'Bagus sekali!',
        ]);

        $response = $this->actingAs($student)->get(route('assignments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Assignments/Index')
            ->has('assignments', 1)
            ->where('assignments.0.submissions.0.status', 'graded')
            ->where('assignments.0.submissions.0.score', 90)
        );
    }
}
