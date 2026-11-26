<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendanceRecordPolicy
{
    /** 管理者はすべての操作を許可する。それ以外は各メソッドで判定する */
    public function before(User $user, string $ability): ?bool
    {
        return $user->role === User::ROLE_ADMIN ? true : null;
    }

    /** 本人の勤怠のみ更新できる（FN067） */
    public function update(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }

    /** 本人の勤怠のみ削除できる（FN067） */
    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }
}
