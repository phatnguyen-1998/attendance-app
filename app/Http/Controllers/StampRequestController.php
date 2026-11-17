<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\StampRequestController as AdminStampRequestController;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StampRequestController extends Controller
{
    public function index(Request $request): View
    {
        // 管理者は全ユーザーの申請一覧（PG12）を表示する
        if ($request->user()->role === User::ROLE_ADMIN) {
            return app(AdminStampRequestController::class)->index();
        }
        $user = $request->user();

        $formattedApplications = $user->stampRequests()
            ->with('AttendanceRecord')
            ->latest()
            ->get()
            ->map(fn ($application) => [
                'id' => $application->id,
                'approval_status' => $application->approval_status,
                'date' => $application->new_date->format('Y/m/d'),
                'comment' => $application->comment,
                'application_date' => $application->application_date->format('Y/m/d'),
            ]);

        return view('user.user-application-list', compact('user', 'formattedApplications'));
    }

    public function show(Request $request, $id): RedirectResponse
    {
        $application = $request->user()->stampRequests()->findOrFail($id);

        return redirect()->route('attendance.show', $application->attendance_id);
    }
}
