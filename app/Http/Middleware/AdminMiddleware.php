<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 管理者以外（一般ユーザー）は自分の打刻画面へ戻す
        if ($request->user()?->role !== User::ROLE_ADMIN) {
            return redirect()->route('attendance.index');
        }

        return $next($request);
    }
}
