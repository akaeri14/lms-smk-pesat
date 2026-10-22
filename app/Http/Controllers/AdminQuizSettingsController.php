<?php

namespace App\Http\Controllers;

use App\Models\QuizAudioTrack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdminQuizSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('Admin/QuizSettings/Index', [
            'tracks' => QuizAudioTrack::query()->orderBy('sort_order')->orderBy('title')->get(),
            'phpUploadLimitMb' => round($this->iniBytes('upload_max_filesize') / 1024 / 1024, 1),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $file = $request->file('audio');
        if (! $file || $file->getError() === UPLOAD_ERR_INI_SIZE) {
            return back()->withErrors(['audio' => 'File tidak berhasil diterima server. Pastikan upload_max_filesize dan post_max_size PHP minimal 100M.']);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'audio' => ['required', 'file', 'mimes:mp3,wav,ogg,mpeg,mpga', 'max:102400'],
        ], [
            'audio.mimes' => 'Format audio harus MP3, WAV, OGG, MPEG, atau MPGA.',
            'audio.max' => 'Ukuran audio maksimal 100 MB.',
        ]);

        QuizAudioTrack::create([
            'title' => $validated['title'],
            'path' => $file->store('audio', 'public'),
            'is_active' => true,
            'sort_order' => (QuizAudioTrack::max('sort_order') ?? -1) + 1,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Audio berhasil ditambahkan ke library kuis.');
    }

    public function update(Request $request, QuizAudioTrack $track): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);
        $track->update($validated);

        return back()->with('success', 'Pengaturan audio berhasil diperbarui.');
    }

    public function destroy(Request $request, QuizAudioTrack $track): RedirectResponse
    {
        $this->authorizeAdmin($request);
        Storage::disk('public')->delete($track->path);
        $track->delete();

        return back()->with('success', 'Audio berhasil dihapus dari library.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Hanya Admin yang dapat mengatur library audio kuis.');
    }

    private function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
