<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Models\Assignment;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Assignment::class);

        $user = $request->user();

        $assignments = Assignment::query()
            ->with([
                'schoolClass' => fn ($query) => $query->withCount('students'),
                'creator:id,name',
            ])
            ->withCount('submissions')
            ->when($user->role === 'guru', fn ($query) => $query->whereHas('schoolClass', fn ($query) => $query->where('teacher_id', $user->id)))
            ->when($user->role === 'siswa', fn ($query) => $query
                ->whereHas('schoolClass.students', fn ($query) => $query->whereKey($user))
                ->with(['submissions' => fn ($query) => $query->where('student_id', $user->id)])
            )
            ->orderBy('deadline')
            ->get();

        return Inertia::render('Assignments/Index', [
            'assignments' => $assignments,
        ]);
    }

    public function create(Request $request, SchoolClass $schoolClass): Response
    {
        $this->authorize('create', [Assignment::class, $schoolClass]);

        return Inertia::render('Assignments/Create', [
            'schoolClass' => $schoolClass->only('id', 'name', 'subject'),
        ]);
    }

    public function store(StoreAssignmentRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('create', [Assignment::class, $schoolClass]);

        $data = $request->safe()->except('attachment');

        if ($request->hasFile('attachment')) {
            $attachment = $request->file('attachment');
            $data['file_path'] = $attachment->storeAs('assignments', Str::uuid().'.'.$attachment->extension(), 'public');
        }

        $assignment = $schoolClass->assignments()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return Redirect::route('assignments.show', $assignment)->with('success', 'Tugas berhasil dibuat.');
    }

    public function show(Assignment $assignment): Response
    {
        $this->authorize('view', $assignment);

        $assignment->load(['schoolClass:id,name,subject,teacher_id', 'creator:id,name']);

        $user = request()->user();
        $submission = null;
        $submissions = null;

        if ($user->role === 'siswa') {
            $submission = $assignment->submissions()
                ->where('student_id', $user->id)
                ->first();
        } elseif ($user->can('update', $assignment)) {
            $submissions = $assignment->submissions()
                ->with('student:id,name,email')
                ->latest('submitted_at')
                ->get();
        }

        return Inertia::render('Assignments/Show', [
            'assignment' => $assignment,
            'canManage' => $user->can('update', $assignment),
            'submission' => $submission,
            'submissions' => $submissions,
        ]);
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        $data = $request->safe()->except('attachment');

        if ($request->hasFile('attachment')) {
            Storage::disk('public')->delete($assignment->file_path);
            $attachment = $request->file('attachment');
            $data['file_path'] = $attachment->storeAs('assignments', Str::uuid().'.'.$attachment->extension(), 'public');
        }

        $assignment->update($data);

        return Redirect::route('assignments.show', $assignment)->with('success', 'Tugas berhasil diperbarui.');
    }

    public function destroy(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authorize('delete', $assignment);

        Storage::disk('public')->delete($assignment->file_path);
        $assignment->delete();

        return Redirect::route('classes.show', $assignment->class_id)->with('success', 'Tugas berhasil dihapus.');
    }
}
