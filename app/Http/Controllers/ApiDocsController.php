<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class ApiDocsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('ApiDocs/Index', [
            'baseUrl' => rtrim((string) config('app.url'), '/'),
            'tokens' => $request->user()->tokens()->latest()->get()->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'kind' => in_array('sso:login', $token->abilities ?? [], true) ? 'sso' : 'api',
                'abilities' => $token->abilities ?? [],
                'plain_token' => $this->decryptPlainToken($token->plain_text_token),
                'created_at' => $token->created_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function createToken(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['api', 'sso'])],
        ]);

        $isSso = $validated['type'] === 'sso';
        $token = $request->user()->createToken(
            $validated['name'],
            $isSso ? ['sso:login'] : ['api:read'],
            now()->addMinutes($isSso ? 10 : 90),
        );
        $this->storePlainToken($token);

        return back()->with([
            'success' => $isSso
                ? 'SSO token berhasil dibuat dan hanya berlaku selama 10 menit.'
                : 'API token berhasil dibuat dan berlaku selama 90 hari.',
        ]);
    }

    public function destroyApiToken(Request $request, int $token): RedirectResponse
    {
        return $this->destroyToken($request, $token, 'api');
    }

    public function destroySsoToken(Request $request, int $token): RedirectResponse
    {
        return $this->destroyToken($request, $token, 'sso');
    }

    private function destroyToken(Request $request, int $token, string $kind): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $accessToken = $request->user()->tokens()->whereKey($token)->first();

        abort_unless($accessToken, 404, 'Token tidak ditemukan.');

        $isSso = in_array('sso:login', $accessToken->abilities ?? [], true);
        abort_unless(($kind === 'sso') === $isSso, 403, 'Jenis token tidak sesuai.');

        $accessToken->delete();

        return back()->with('success', ($kind === 'sso' ? 'SSO token' : 'API token').' berhasil dicabut.');
    }

    public function ssoAuthorize(Request $request): RedirectResponse
    {
        $plainTextToken = trim((string) $request->query('token'));
        $accessToken = $plainTextToken !== '' ? PersonalAccessToken::findToken($plainTextToken) : null;

        abort_unless($accessToken && $accessToken->tokenable && $accessToken->can('sso:login'), 401, 'SSO token tidak valid atau sudah kedaluwarsa.');
        abort_if($accessToken->expires_at && $accessToken->expires_at->isPast(), 401, 'SSO token sudah kedaluwarsa.');

        $user = $accessToken->tokenable;
        $accessToken->delete();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Hanya Admin yang dapat mengakses API Documentation.');
    }

    private function storePlainToken($token): void
    {
        $token->accessToken->forceFill([
            'plain_text_token' => encrypt($token->plainTextToken),
        ])->save();
    }

    private function decryptPlainToken(?string $encryptedToken): ?string
    {
        if (! $encryptedToken) {
            return null;
        }

        try {
            return decrypt($encryptedToken);
        } catch (\Throwable) {
            return null;
        }
    }
}
