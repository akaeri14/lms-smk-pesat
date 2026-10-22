<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\SchoolClass;
use App\Models\User;

class AssignmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'guru', 'siswa'], true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Assignment $assignment): bool
    {
        return $user->role === 'admin'
            || $assignment->schoolClass->teacher_id === $user->id
            || $assignment->schoolClass->students()->whereKey($user)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, SchoolClass $schoolClass): bool
    {
        return $user->role === 'admin'
            || ($user->role === 'guru' && $schoolClass->teacher_id === $user->id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Assignment $assignment): bool
    {
        return $user->role === 'admin' || $assignment->schoolClass->teacher_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }
}
