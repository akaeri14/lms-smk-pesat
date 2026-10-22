<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Middleware guard: hanya admin yang boleh mengakses modul ini.
     */
    private function authorizeAdmin(Request $request): void
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Hanya Admin yang dapat mengakses halaman ini.');
        }
    }

    /**
     * Tampilkan daftar semua user dengan fitur filter role dan pencarian nama.
     */
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $users = User::query()
            ->when($request->role, fn ($q) => $q->where('role', $request->role))
            ->when($request->search, fn ($q) => $q->where(function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            }))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users'   => $users,
            'filters' => $request->only('role', 'search'),
        ]);
    }

    /**
     * Simpan user baru ke database.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', Rule::in(['admin', 'guru', 'siswa'])],
            'phone'    => ['nullable', 'string', 'max:20'],
        ], [
            'name.required'     => 'Nama pengguna wajib diisi.',
            'email.required'    => 'Alamat email wajib diisi.',
            'email.unique'      => 'Alamat email ini sudah digunakan oleh pengguna lain.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min'      => 'Kata sandi minimal 8 karakter.',
            'role.required'     => 'Peran (role) pengguna wajib dipilih.',
            'role.in'           => 'Peran yang dipilih tidak valid.',
        ]);

        User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
            'phone'    => $validated['phone'] ?? null,
        ]);

        return back()->with('success', "Pengguna '{$validated['name']}' berhasil ditambahkan.");
    }

    /**
     * Perbarui data user yang sudah ada.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role'     => ['required', Rule::in(['admin', 'guru', 'siswa'])],
            'phone'    => ['nullable', 'string', 'max:20'],
        ], [
            'name.required'  => 'Nama pengguna wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.unique'   => 'Alamat email ini sudah digunakan oleh pengguna lain.',
            'password.min'   => 'Kata sandi baru minimal 8 karakter.',
            'role.required'  => 'Peran (role) pengguna wajib dipilih.',
            'role.in'        => 'Peran yang dipilih tidak valid.',
        ]);

        $data = [
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'role'  => $validated['role'],
            'phone' => $validated['phone'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        // Cegah admin menghapus role admin-nya sendiri
        if ($user->id === $request->user()->id && $validated['role'] !== 'admin') {
            return back()->withErrors(['role' => 'Anda tidak dapat mengubah role akun Anda sendiri.']);
        }

        $user->update($data);

        return back()->with('success', "Data pengguna '{$user->name}' berhasil diperbarui.");
    }

    /**
     * Hapus user dari database.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        // Cegah admin menghapus akunnya sendiri
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['general' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
        }

        $name = $user->name;
        $user->delete();

        return back()->with('success', "Pengguna '{$name}' berhasil dihapus dari sistem.");
    }

    /** Jalankan sinkronisasi Profiling API dari panel admin. */
    public function syncProfiling(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $exitCode = Artisan::call('sync:profiling-data', ['--fresh' => true]);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            return back()->with('error', 'Sinkronisasi Profiling gagal.'.($output ? ' '.$output : ''));
        }

        return back()->with('success', 'Sinkronisasi Profiling berhasil dijalankan.');
    }
}
