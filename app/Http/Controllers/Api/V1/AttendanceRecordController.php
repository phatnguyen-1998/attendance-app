<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class AttendanceRecordController extends Controller
{
    /** 勤怠一覧を取得する（FN058）。user_id・date・month で絞り込み、ページネーション対応 */
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        $records = Attendance::with(['user', 'breakTimes'])
            ->when($request->validated('user_id'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($request->validated('date'), fn ($query, $date) => $query->where('work_date', $date))
            ->when($request->validated('month'), function ($query, $month) {
                $start = Carbon::createFromFormat('!Y-m', $month);

                return $query->whereBetween('work_date', [
                    $start->toDateString(),
                    $start->copy()->endOfMonth()->toDateString(),
                ]);
            })
            ->latest('work_date')
            ->paginate($request->validated('per_page') ?? 20);

        return AttendanceRecordResource::collection($records);
    }

    public function store(Request $request)
    {
        //
    }

    /** 勤怠詳細を取得する（FN059）。ユーザー・休憩・修正申請を含める */
    public function show(Attendance $attendanceRecord): AttendanceRecordResource
    {
        return new AttendanceRecordResource(
            $attendanceRecord->load(['user', 'breakTimes', 'stampRequests'])
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
