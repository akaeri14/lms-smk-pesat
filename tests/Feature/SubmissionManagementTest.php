<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\SchoolClass;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_can_submit_assignment(): void
    {
        Storage::fake('public');

        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $student = User::factory()->create(['role' => 'siswa']);
        $schoolClass->students()->attach($student, ['joined_at' => now()]);

        $assignment = Assignment::factory()->create([
            'class_id' => $schoolClass->id,
            'created_by' => $teacher->id,
            'deadline' => now()->addDays(2),
        ]);

        $response = $this->actingAs($student)->post(route('assignments.submissions.store', $assignment), [
            'file' => UploadedFile::fake()->create('jawaban-tugas.pdf', 500, 'application/pdf'),
            'notes' => 'Berikut tugas yang telah saya selesaikan.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'submission_text' => 'Berikut tugas yang telah saya selesaikan.',
            'status' => 'submitted',
        ]);

        $submission = Submission::where('assignment_id', $assignment->id)->first();
        Storage::disk('public')->assertExists($submission->file_path);
    }

    public function test_submission_is_marked_late_if_submitted_after_deadline(): void
    {
        Storage::fake('public');

        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $student = User::factory()->create(['role' => 'siswa']);
        $schoolClass->students()->attach($student, ['joined_at' => now()]);

        $assignment = Assignment::factory()->create([
            'class_id' => $schoolClass->id,
            'created_by' => $teacher->id,
            'deadline' => now()->subDay(), // Deadline lewat kemarin
        ]);

        $this->actingAs($student)->post(route('assignments.submissions.store', $assignment), [
            'file' => UploadedFile::fake()->create('jawaban-telat.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertRedirect();

        $this->assertDatabaseHas('submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'status' => 'late',
        ]);
    }

    public function test_student_cannot_submit_file_larger_than_10mb(): void
    {
        Storage::fake('public');

        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $student = User::factory()->create(['role' => 'siswa']);
        $schoolClass->students()->attach($student, ['joined_at' => now()]);

        $assignment = Assignment::factory()->create([
            'class_id' => $schoolClass->id,
            'created_by' => $teacher->id,
            'deadline' => now()->addDays(2),
        ]);

        // 10241 KB = > 10MB
        $response = $this->actingAs($student)->post(route('assignments.submissions.store', $assignment), [
            'file' => UploadedFile::fake()->create('tugas-terlalu-besar.pdf', 10241, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors(['file']);
        $this->assertDatabaseMissing('submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_unenrolled_student_cannot_submit_assignment(): void
    {
        Storage::fake('public');

        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $otherStudent = User::factory()->create(['role' => 'siswa']); // Tidak terdaftar di kelas

        $assignment = Assignment::factory()->create([
            'class_id' => $schoolClass->id,
            'created_by' => $teacher->id,
        ]);

        $response = $this->actingAs($otherStudent)->post(route('assignments.submissions.store', $assignment), [
            'file' => UploadedFile::fake()->create('jawaban.pdf', 100, 'application/pdf'),
        ]);

        $response->assertForbidden();
    }

    public function test_teacher_cannot_submit_as_student(): void
    {
        Storage::fake('public');

        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);

        $assignment = Assignment::factory()->create([
            'class_id' => $schoolClass->id,
            'created_by' => $teacher->id,
        ]);

        $response = $this->actingAs($teacher)->post(route('assignments.submissions.store', $assignment), [
            'file' => UploadedFile::fake()->create('jawaban.pdf', 100, 'application/pdf'),
        ]);

        $response->assertForbidden();
    }

    public function test_student_resubmission_replaces_old_file(): void
    {
        Storage::fake('public');

        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $student = User::factory()->create(['role' => 'siswa']);
        $schoolClass->students()->attach($student, ['joined_at' => now()]);

        $assignment = Assignment::factory()->create([
            'class_id' => $schoolClass->id,
            'created_by' => $teacher->id,
            'deadline' => now()->addDays(2),
        ]);

        // Pengumpulan pertama
        $this->actingAs($student)->post(route('assignments.submissions.store', $assignment), [
            'file' => UploadedFile::fake()->create('versi-1.pdf', 100, 'application/pdf'),
            'notes' => 'Draft awal',
        ])->assertRedirect();

        $submission1 = Submission::where('assignment_id', $assignment->id)->first();
        $oldFilePath = $submission1->file_path;
        Storage::disk('public')->assertExists($oldFilePath);

        // Pengumpulan kedua (re-submit)
        $this->actingAs($student)->post(route('assignments.submissions.store', $assignment), [
            'file' => UploadedFile::fake()->create('versi-final.pdf', 200, 'application/pdf'),
            'notes' => 'Versi revisi final',
        ])->assertRedirect();

        $submission2 = Submission::where('assignment_id', $assignment->id)->first();

        // Database hanya punya 1 record submission (updateOrCreate)
        $this->assertEquals(1, Submission::where('assignment_id', $assignment->id)->count());
        $this->assertEquals('Versi revisi final', $submission2->submission_text);

        // File lama terhapus dan file baru tersimpan
        Storage::disk('public')->assertMissing($oldFilePath);
        Storage::disk('public')->assertExists($submission2->file_path);
    }

    public function test_teacher_can_grade_student_submission(): void
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

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'file_path' => 'submissions/sample.pdf',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($teacher)->patch(route('submissions.grade', $submission), [
            'score' => 95,
            'teacher_feedback' => 'Kerja bagus, penjelasan sangat lengkap!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('submissions', [
            'id' => $submission->id,
            'score' => 95,
            'teacher_feedback' => 'Kerja bagus, penjelasan sangat lengkap!',
            'status' => 'graded',
        ]);
        $this->assertNotNull($submission->fresh()->graded_at);
    }

    public function test_teacher_cannot_give_score_exceeding_max_score(): void
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

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'file_path' => 'submissions/sample.pdf',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($teacher)->patch(route('submissions.grade', $submission), [
            'score' => 105, // Melebihi max_score (100)
        ]);

        $response->assertSessionHasErrors(['score']);
        $this->assertEquals('submitted', $submission->fresh()->status);
    }

    public function test_other_teacher_cannot_grade_submission(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $otherTeacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $student = User::factory()->create(['role' => 'siswa']);
        $schoolClass->students()->attach($student, ['joined_at' => now()]);

        $assignment = Assignment::factory()->create([
            'class_id' => $schoolClass->id,
            'created_by' => $teacher->id,
        ]);

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'file_path' => 'submissions/sample.pdf',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($otherTeacher)->patch(route('submissions.grade', $submission), [
            'score' => 90,
        ]);

        $response->assertForbidden();
    }

    public function test_student_cannot_grade_submission(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $student = User::factory()->create(['role' => 'siswa']);
        $schoolClass->students()->attach($student, ['joined_at' => now()]);

        $assignment = Assignment::factory()->create([
            'class_id' => $schoolClass->id,
            'created_by' => $teacher->id,
        ]);

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'file_path' => 'submissions/sample.pdf',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($student)->patch(route('submissions.grade', $submission), [
            'score' => 100,
        ]);

        $response->assertForbidden();
    }
}
