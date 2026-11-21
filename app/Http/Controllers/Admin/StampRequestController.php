<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StampRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

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

    /** 修正申請承認画面（PG13） */
    public function show(string $attendance_correct_request_id): View
    {
        $application = StampRequest::with(['user', 'AttendanceRecord', 'proposalBreaks'])
            ->findOrFail($attendance_correct_request_id);
        $user = $application->user; // 申請したユーザー

        return view('admin.admin-application-detail', compact('user', 'application'));
    }

    /** 承認：申請内容を勤怠に反映し、申請を承認済みにする（FN051） */
    public function approve(string $attendance_correct_request_id): RedirectResponse
    {
        $application = StampRequest::with(['AttendanceRecord', 'proposalBreaks'])
            ->findOrFail($attendance_correct_request_id);

        // 承認済みの申請は二重に反映しない
        if ($application->status === StampRequest::STATUS_PENDING) {
            DB::transaction(function () use ($application) {
                $attendance = $application->AttendanceRecord;

                $attendance->update([
                    'clock_in' => $application->new_clock_in,
                    'clock_out' => $application->new_clock_out,
                    'note' => $application->comment,
                ]);

                // 休憩は申請内容で置き換える
                $attendance->breakTimes()->delete();
                foreach ($application->proposalBreaks as $break) {
                    $attendance->breakTimes()->create([
                        'break_in' => $break->break_in,
                        'break_out' => $break->break_out,
                    ]);
                }

                $application->update([
                    'status' => StampRequest::STATUS_APPROVED,
                    'approved_at' => now(),
                ]);
            });
        }

        return redirect()->route('stamp_correction_request.approve', $application->id);
    }
}
