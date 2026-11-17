<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StampRequest;
use Illuminate\Contracts\View\View;

class StampRequestController extends Controller
{
    /** 申請一覧（管理者）：全ユーザーの申請を表示する（PG12） */
    public function index(): View
    {
        $applications = StampRequest::with(['user', 'AttendanceRecord'])
            ->latest()
            ->get();

        return view('admin.admin-application-list', compact('applications'));
    }
}
