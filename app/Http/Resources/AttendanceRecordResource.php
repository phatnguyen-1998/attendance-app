<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'date' => $this->work_date->toDateString(),
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'total_time' => $this->total_time ? substr($this->total_time, 0, 5) : null,
            'total_break_time' => $this->total_break_time ? substr($this->total_break_time, 0, 5) : null,
            'breaks' => $this->whenLoaded('breakTimes', fn () => $this->breakTimes->map(fn ($break) => [
                'id' => $break->id,
                'break_in' => $break->break_in,
                'break_out' => $break->break_out,
            ])),
            'applications' => $this->whenLoaded('stampRequests', fn () => $this->stampRequests->map(fn ($stampRequest) => [
                'id' => $stampRequest->id,
                'status' => $stampRequest->approvalStatus,
            ])),
            'comment' => $this->note,
        ];
    }
}
