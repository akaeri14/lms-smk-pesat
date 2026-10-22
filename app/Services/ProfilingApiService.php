<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ProfilingApiService
{
    private array $config;

    /** @var array<int, string> */
    private array $warnings = [];

    public function __construct()
    {
        $this->config = config('services.profiling', []);
    }

    /** @return array<int, array<string, mixed>> */
    public function students(array $query = [], bool $fresh = false): array
    {
        return $this->getCollection('students', $query, $fresh);
    }

    /** @return array<int, array<string, mixed>> */
    public function teachers(array $query = [], bool $fresh = false): array
    {
        // Endpoint guru masih bersifat opsional sampai tersedia di server Profiling.
        return $this->getCollection('teachers', $query, true);
    }

    /** @return array<int, string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return array<int, array<string, mixed>> */
    public function profiles(array $query = [], bool $fresh = false): array
    {
        return $this->getCollection('profiles', $query, $fresh);
    }

    /**
     * Sinkronkan data siswa dan guru dari API ke tabel users lokal.
     * Record tanpa email tidak dibuat karena email adalah identitas login lokal.
     *
     * @return array<string, mixed>
     */
    public function syncUsers(bool $fresh = false, bool $syncStudents = true, bool $syncTeachers = true): array
    {
        $result = [
            'students' => 0,
            'teachers' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'student_stats' => ['synced' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0],
            'teacher_stats' => ['synced' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0],
        ];

        if ($syncStudents) {
            $result['student_stats'] = $this->syncUserCollection($this->students([], $fresh), 'siswa');
            $result['students'] = $result['student_stats']['synced'];
        }

        if ($syncTeachers) {
            $result['teacher_stats'] = $this->syncUserCollection($this->teachers([], $fresh), 'guru');
            $result['teachers'] = $result['teacher_stats']['synced'];
        }

        foreach (['student_stats', 'teacher_stats'] as $statsKey) {
            foreach (['created', 'updated', 'failed', 'skipped'] as $key) {
                $result[$key] += $result[$statsKey][$key];
            }
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function getCollection(string $resource, array $query, bool $fresh): array
    {
        $endpoint = $this->config['endpoints'][$resource] ?? $resource;
        $records = [];
        $page = 1;

        do {
            $pageQuery = [...$query, 'page' => $page];
            $payload = $this->fetchPage($resource, $endpoint, $pageQuery, $fresh);
            $records = [...$records, ...$this->extractCollection($payload)];

            $currentPage = (int) data_get($payload, 'meta.current_page', data_get($payload, 'current_page', $page));
            $lastPage = (int) data_get($payload, 'meta.last_page', data_get($payload, 'last_page', 0));
            $nextPageUrl = data_get($payload, 'links.next', data_get($payload, 'next_page_url'));

            $hasNextPage = $lastPage > 0
                ? $currentPage < $lastPage
                : $nextPageUrl !== null && $nextPageUrl !== false;

            $page++;
        } while ($hasNextPage);

        return $records;
    }

    /** @return array<string, mixed> */
    private function fetchPage(string $resource, string $endpoint, array $query, bool $fresh): array
    {
        $cacheKey = 'profiling.api.'.md5($resource.'|'.http_build_query($query));
        $ttl = (int) ($this->config['cache_ttl'] ?? 300);

        $fetch = function () use ($endpoint, $query, $resource): array {
            try {
                $response = $this->request()->get($endpoint, $query);

                if ($response->failed()) {
                    if ($resource === 'teachers' && $response->status() === 404) {
                        $message = 'Endpoint Guru belum tersedia di API Profiling, sinkronisasi guru dilewati';
                        $this->warnings[] = $message;
                        Log::warning($message, ['endpoint' => $endpoint]);

                        return [];
                    }

                    throw new RuntimeException($response->status().' - '.$response->body());
                }
            } catch (\Throwable $exception) {
                Log::error('Profiling API request failed.', [
                    'resource' => $resource,
                    'endpoint' => $endpoint,
                    'query' => $query,
                    'message' => $exception->getMessage(),
                ]);

                throw new RuntimeException($exception->getMessage(), (int) $exception->getCode(), $exception);
            }

            $payload = $response->json();
            return is_array($payload) ? $payload : [];
        };

        if ($fresh || $ttl <= 0) {
            return $fetch();
        }

        return Cache::remember($cacheKey, now()->addSeconds($ttl), $fetch);
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
                'X-API-KEY' => config('services.profiling.key'),
            ])
            ->baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->acceptJson()
            ->timeout((int) ($this->config['timeout'] ?? 15))
            // Jangan lempar exception otomatis agar response 404 teachers dapat
            // ditangani berdasarkan status dan body di getCollection().
            ->retry(2, 250, null, false);
    }

    /** @return array<int, array<string, mixed>> */
    private function extractCollection(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        foreach (['data', 'results', 'items', 'students', 'teachers', 'profiles'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_is_list($payload[$key]) ? $payload[$key] : [$payload[$key]];
            }
        }

        return array_is_list($payload) ? $payload : [$payload];
    }

    /** @param array<int, array<string, mixed>> $records */
    private function syncUserCollection(array $records, string $role): array
    {
        $stats = ['synced' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($records as $record) {
            $externalId = $this->value($record, ['id', 'uuid', 'external_id', 'profile_id', 'user.id']);
            $nisn = $this->value($record, ['nisn', 'nis', 'national_student_id', 'student_number']);
            $apiName = $this->resolveApiName($record);
            $apiEmail = $this->resolveApiEmail($record);

            if ($externalId === null && ($nisn === null || $nisn === '') && $apiName === '') {
                $stats['skipped']++;
                continue;
            }

            try {
                DB::transaction(function () use ($record, $role, $apiEmail, $apiName, $externalId, $nisn, &$stats): void {
                    $existing = $this->findExistingUser($externalId, $nisn, $apiName);
                    $profilingAttributes = [
                        'profiling_data' => $record,
                        'profiling_external_id' => $externalId !== null
                            ? (string) $externalId
                            : $existing?->profiling_external_id,
                        'profiling_synced_at' => now(),
                    ];

                    if ($existing) {
                        // Lindungi kredensial lokal: email, password, nama, role,
                        // dan field akun lain tidak disentuh saat user sudah ada.
                        $existing->forceFill($profilingAttributes)->save();
                        $stats['updated']++;
                    } else {
                        $email = $apiEmail !== ''
                            ? $apiEmail
                            : $this->fallbackEmail($record, $nisn);

                        if ($email === '') {
                            $stats['skipped']++;
                            return;
                        }

                        User::query()->create([
                            'name' => $apiName !== '' ? $apiName : 'Siswa Profiling',
                            'email' => $email,
                            // Schema LMS menggunakan role "siswa", bukan "student".
                            'role' => $role,
                            'password' => Hash::make('password'),
                            ...$profilingAttributes,
                        ]);
                        $stats['created']++;
                    }

                    $stats['synced']++;
                });
            } catch (\Throwable $exception) {
                $stats['failed']++;
                Log::error('Profiling user sync failed.', [
                    'email' => $apiEmail !== '' ? $apiEmail : null,
                    'external_id' => $externalId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function findExistingUser(mixed $externalId, mixed $nisn, string $apiName): ?User
    {
        if ($externalId !== null && $externalId !== '') {
            $user = User::query()->where('profiling_external_id', (string) $externalId)->first();
            if ($user) {
                return $user;
            }
        }

        if ($nisn !== null && $nisn !== '') {
            $nisn = $this->normaliseIdentifier($nisn);
            $user = User::query()
                ->whereNotNull('profiling_data')
                ->get()
                ->first(function (User $candidate) use ($nisn): bool {
                    $profile = is_array($candidate->profiling_data) ? $candidate->profiling_data : [];

                    foreach (['nisn', 'nis', 'national_student_id', 'student_number'] as $key) {
                        if ($this->normaliseIdentifier(Arr::get($profile, $key)) === $nisn) {
                            return true;
                        }
                    }

                    return false;
                });

            if ($user) {
                return $user;
            }
        }

        if ($apiName === '') {
            return null;
        }

        return User::query()
            ->whereNotNull('name')
            ->get()
            ->first(fn (User $candidate): bool =>
                $this->normaliseName($candidate->name) === $this->normaliseName($apiName)
            );
    }

    private function normaliseIdentifier(mixed $value): string
    {
        return trim((string) $value);
    }

    private function normaliseName(mixed $value): string
    {
        $name = trim((string) $value);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return mb_strtolower($name, 'UTF-8');
    }

    private function resolveApiName(array $record): string
    {
        return trim((string) $this->value($record, [
            'name', 'full_name', 'nama', 'nama_lengkap', 'user.name', 'profile.name',
        ]));
    }

    private function resolveApiEmail(array $record): string
    {
        $email = strtolower(trim((string) $this->value($record, [
            'email', 'email_address', 'mail', 'user.email', 'user.email_address', 'profile.email', 'profile.email_address',
        ])));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function fallbackEmail(array $record, mixed $nisn): string
    {
        $identifier = $nisn ?? $this->value($record, ['nis', 'nisn', 'national_student_id', 'student_number']);
        if ($identifier === null || $identifier === '') {
            return '';
        }

        $identifier = Str::slug((string) $identifier, '-');
        return "{$identifier}@smkpesat.sch.id";
    }

    private function value(array $record, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($record, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normaliseStatus(mixed $status): string
    {
        if (is_bool($status)) {
            return $status ? 'active' : 'inactive';
        }

        return in_array(strtolower((string) $status), ['inactive', 'disabled', 'nonactive', 'false', '0'], true)
            ? 'inactive'
            : 'active';
    }
}
