<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
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

    /** 勤怠を登録する（FN060） */
    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $attendance = $request->user()->attendances()->create($request->attendanceAttributes());

        return (new AttendanceRecordResource($attendance->load(['user', 'breakTimes'])))
            ->response()
            ->setStatusCode(201);
    }

    /** 勤怠詳細を取得する（FN059）。ユーザー・休憩・修正申請を含める */
    public function show(Attendance $attendanceRecord): AttendanceRecordResource
    {
        return new AttendanceRecordResource(
            $attendanceRecord->load(['user', 'breakTimes', 'stampRequests'])
        );
    }

    /** 勤怠を更新する（FN061）。送信された項目のみ更新する */
    public function update(UpdateAttendanceRecordRequest $request, Attendance $attendanceRecord): AttendanceRecordResource
    {
        $this->authorize('update', $attendanceRecord);

        $attendanceRecord->update($request->attendanceAttributes());

        return new AttendanceRecordResource($attendanceRecord->load(['user', 'breakTimes']));
    }

    /** 勤怠を削除する（FN062）。休憩と修正申請は外部キーで連鎖削除される */
    public function destroy(Attendance $attendanceRecord): Response
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}
