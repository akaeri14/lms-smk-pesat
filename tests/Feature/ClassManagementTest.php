<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_a_class_with_a_unique_six_character_code(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);

        $this->actingAs($teacher)->post(route('classes.store'), [
            'name' => 'XII RPL 1',
            'subject' => 'Pemrograman Web',
            'academic_year' => '2026/2027',
        ])->assertRedirect();

        $this->assertDatabaseHas('classes', [
            'name' => 'XII RPL 1',
            'teacher_id' => $teacher->id,
        ]);

        $this->assertSame(6, strlen(SchoolClass::query()->sole()->code));
    }

    public function test_student_can_join_an_active_class_but_cannot_create_one(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $schoolClass = SchoolClass::factory()->create(['code' => 'RPL123']);

        $this->actingAs($student)->post(route('classes.store'), [
            'name' => 'Tidak Diizinkan',
            'subject' => 'Tes',
            'academic_year' => '2026/2027',
        ])->assertForbidden();

        $this->actingAs($student)->post(route('classes.join'), ['code' => 'RPL123'])->assertRedirect(route('classes.show', $schoolClass));

        $this->assertDatabaseHas('class_user', ['class_id' => $schoolClass->id, 'user_id' => $student->id]);
    }
}
