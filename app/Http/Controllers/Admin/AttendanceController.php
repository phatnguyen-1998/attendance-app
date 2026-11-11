<?php

namespace App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        // ?date=YYYY-MM-DD。未指定・不正な値は今日にする
        $date = Validator::make($request->query(), ['date' => 'required|date_format:Y-m-d'])->passes()
            ? Carbon::createFromFormat('!Y-m-d', $request->query('date'))
            : today();

        return view('admin.admin-attendance-list', [
            'date' => $date,
            'previousDay' => $date->copy()->subDay()->toDateString(),
            'nextDay' => $date->copy()->addDay()->toDateString(),
            'users' => User::where('role', User::ROLE_USER)->orderBy('id')->get(),
            'attendanceRecords' => Attendance::with('breakTimes')->whereDate('work_date', $date)->get(),
        ]);
    }
}
