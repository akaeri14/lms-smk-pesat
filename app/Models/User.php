<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'avatar', 'phone', 'bio', 'status', 'profiling_external_id', 'profiling_synced_at', 'profiling_data'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profiling_synced_at' => 'datetime',
            'profiling_data' => 'array',
        ];
    }

    public function classesTaught(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'teacher_id');
    }

    public function enrolledClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_user', 'user_id', 'class_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function assignmentsCreated(): HasMany
    {
        return $this->hasMany(Assignment::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'student_id');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'user_id');
    }
}
