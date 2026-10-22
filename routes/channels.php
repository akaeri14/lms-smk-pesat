<?php

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('quiz.{quiz}', function (User $user, Quiz $quiz): bool {
    if ($user->role === 'admin') {
        return true;
    }

    $schoolClass = $quiz->schoolClass;

    return $user->role === 'guru'
        ? $schoolClass?->teacher_id === $user->id
        : $schoolClass?->students()->whereKey($user->id)->exists();
});
