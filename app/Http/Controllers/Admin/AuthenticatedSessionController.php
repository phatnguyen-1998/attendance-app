<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('admin.admin-login');
    }

    public function store(AdminLoginRequest $request)
    {
        $credentials = $request->validated();

        // role が管理者のアカウントのみログイン可
        if (Auth::attempt([...$credentials, 'role' => User::ROLE_ADMIN])) {
            $request->session()->regenerate();

            return redirect()->route('admin.attendance.list');
        }

        return back()->withErrors(['email' => 'ログイン情報が登録されていません'])->onlyInput('email');
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        // セッションを破棄し、CSRF トークンを再生成する
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
