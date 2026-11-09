<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampBreak extends Model
{
    use HasFactory;

    protected $fillable = ['stamp_request_id', 'break_start', 'break_end'];

    public function stampRequest(): BelongsTo
    {
        return $this->belongsTo(StampRequest::class);
    }
}
