<?php

namespace App\Http\Controllers;

use App\Http\Requests\JoinClassRequest;
use App\Http\Requests\StoreClassRequest;
use App\Http\Requests\UpdateClassRequest;
use App\Models\Assignment;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClassController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SchoolClass::class);

        $classes = SchoolClass::query()
            ->with('teacher:id,name')
            ->withCount('students')
            ->when($request->user()->role === 'guru', fn ($query) => $query->where('teacher_id', $request->user()->id))
            ->when($request->user()->role === 'siswa', fn ($query) => $query->whereHas('students', fn ($query) => $query->whereKey($request->user())))
            ->latest()
            ->get();

        return Inertia::render('Classes/Index', [
            'classes' => $classes,
            'canCreate' => $request->user()->can('create', SchoolClass::class),
            'canJoin' => $request->user()->can('join', SchoolClass::class),
        ]);
    }

    public function store(StoreClassRequest $request): RedirectResponse
    {
        $schoolClass = SchoolClass::query()->create([
            ...$request->validated(),
            'code' => $this->generateClassCode(),
            'teacher_id' => $request->user()->id,
        ]);

        return Redirect::route('classes.show', $schoolClass)->with('success', 'Kelas berhasil dibuat.');
    }

    public function show(Request $request, SchoolClass $schoolClass): Response
    {
        $this->authorize('view', $schoolClass);

        $user = $request->user();

        $schoolClass->load([
            'teacher:id,name,email',
            'students:id,name,email,avatar',
            'assignments' => fn ($query) => $query
                ->with('creator:id,name')
                ->withCount('submissions')
                ->when($user->role === 'siswa', fn ($q) => $q->with([
                    'submissions' => fn ($subQuery) => $subQuery->where('student_id', $user->id),
                ]))
                ->latest('deadline'),
        ])->loadCount('students');

        return Inertia::render('Classes/Show', [
            'schoolClass' => $schoolClass,
            'canManage' => $request->user()->can('update', $schoolClass),
            'canCreateAssignment' => $request->user()->can('create', [Assignment::class, $schoolClass]),
        ]);
    }

    public function update(UpdateClassRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $schoolClass->update($request->validated());

        return Redirect::route('classes.show', $schoolClass)->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('delete', $schoolClass);

        $schoolClass->delete();

        return Redirect::route('classes.index')->with('success', 'Kelas berhasil dihapus.');
    }

    public function join(JoinClassRequest $request): RedirectResponse
    {
        $schoolClass = SchoolClass::query()->where('code', Str::upper($request->validated('code')))->first();

        if ($schoolClass === null || $schoolClass->status !== 'active') {
            return Redirect::back()->withErrors(['code' => 'Kode kelas tidak ditemukan atau kelas sudah diarsipkan.']);
        }

        $schoolClass->students()->syncWithoutDetaching([
            $request->user()->id => ['joined_at' => now()],
        ]);

        return Redirect::route('classes.show', $schoolClass)->with('success', 'Berhasil bergabung ke kelas.');
    }

    private function generateClassCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (SchoolClass::query()->where('code', $code)->exists());

        return $code;
    }
}
