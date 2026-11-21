<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /** 管理者ログイン画面を表示する */
    public function create(): View
    {
        return view('admin.admin-login');
    }

    /** 管理者のみログインを許可する。失敗時は「ログイン情報が登録されていません」（FN014〜FN016） */
    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        // role が管理者のアカウントのみログイン可
        if (Auth::attempt([...$credentials, 'role' => User::ROLE_ADMIN])) {
            $request->session()->regenerate();

            return redirect()->route('admin.attendance.list');
        }

        return back()->withErrors(['email' => 'ログイン情報が登録されていません'])->onlyInput('email');
    }

    /** ログアウトし、セッションを破棄してログイン画面へ戻す（FN017） */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        // セッションを破棄し、CSRF トークンを再生成する
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
