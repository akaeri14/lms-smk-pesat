<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:admin'])->get('/role-test', fn () => response()->noContent());
    }

    public function test_an_authorized_role_can_access_a_protected_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/role-test')->assertNoContent();
    }

    public function test_an_unauthorized_role_receives_a_forbidden_response(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($student)->get('/role-test')->assertForbidden();
    }
}
