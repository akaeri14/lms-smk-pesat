<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubmissionController extends Controller
{
    /**
     * Simpan atau perbarui pengumpulan tugas siswa.
     */
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        $user = $request->user();

        // Pastikan hanya siswa (atau admin untuk pengujian) yang dapat mengumpulkan tugas
        if ($user->role !== 'siswa' && $user->role !== 'admin') {
            abort(403, 'Hanya siswa yang dapat mengumpulkan tugas.');
        }

        // Pastikan siswa terdaftar pada kelas tugas ini
        $isEnrolled = $assignment->schoolClass->students()->whereKey($user->id)->exists();
        if ($user->role === 'siswa' && ! $isEnrolled) {
            abort(403, 'Anda tidak terdaftar di kelas tugas ini.');
        }

        // Validasi input form: file maksimal 10MB (10240 KB)
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,zip', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'submission_text' => ['nullable', 'string', 'max:1000'],
        ], [
            'file.required' => 'File tugas wajib diunggah.',
            'file.file' => 'File lampiran tidak valid.',
            'file.mimes' => 'Format file tugas harus berupa: pdf, doc, docx, atau zip.',
            'file.max' => 'Ukuran file tugas maksimal 10MB (10.240 KB).',
            'notes.max' => 'Catatan tambahan maksimal 1.000 karakter.',
            'submission_text.max' => 'Catatan tambahan maksimal 1.000 karakter.',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $fileName = Str::uuid().'.'.$extension;

        // Cek pengumpulan sebelumnya jika ada untuk menghapus file lama
        $existing = Submission::where('assignment_id', $assignment->id)
            ->where('student_id', $user->id)
            ->first();

        if ($existing && $existing->file_path && Storage::disk('public')->exists($existing->file_path)) {
            Storage::disk('public')->delete($existing->file_path);
        }

        $filePath = $file->storeAs('submissions', $fileName, 'public');
        $notes = $validated['notes'] ?? $validated['submission_text'] ?? null;

        // Cek apakah pengumpulan terlambat berdasarkan deadline tugas
        $isLate = now()->greaterThan($assignment->deadline);

        Submission::updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'student_id' => $user->id,
            ],
            [
                'file_path' => $filePath,
                'submission_text' => $notes,
                'submitted_at' => now(),
                'status' => $isLate ? 'late' : 'submitted',
            ]
        );

        $statusText = $isLate ? ' (Terlambat)' : '';

        return back()->with('success', "Tugas berhasil dikumpulkan{$statusText}!");
    }

    /**
     * Simpan nilai dan umpan balik tugas siswa oleh Guru.
     */
    public function grade(Request $request, Submission $submission): RedirectResponse
    {
        $user = $request->user();
        $assignment = $submission->assignment;
        $schoolClass = $assignment->schoolClass;

        // Pastikan hanya guru pengampu kelas atau admin yang dapat menilai tugas
        if ($user->role !== 'admin' && ($user->role !== 'guru' || $schoolClass->teacher_id !== $user->id)) {
            abort(403, 'Anda tidak memiliki hak untuk menilai tugas ini.');
        }

        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:'.$assignment->max_score],
            'teacher_feedback' => ['nullable', 'string', 'max:2000'],
        ], [
            'score.required' => 'Nilai tugas wajib diisi.',
            'score.integer' => 'Nilai tugas harus berupa angka bilangan bulat.',
            'score.min' => 'Nilai minimal adalah 0.',
            'score.max' => "Nilai tidak boleh melebihi nilai maksimal tugas ({$assignment->max_score}).",
            'teacher_feedback.max' => 'Masukan/catatan guru maksimal 2.000 karakter.',
        ]);

        $submission->update([
            'score' => $validated['score'],
            'teacher_feedback' => $validated['teacher_feedback'] ?? null,
            'status' => 'graded',
            'graded_at' => now(),
        ]);

        return back()->with('success', 'Nilai dan catatan guru berhasil disimpan!');
    }
}