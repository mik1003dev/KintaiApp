<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StampBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'stamp_id',
        'break_start_at',
        'break_end_at',
    ];

    protected $casts = [
        'break_start_at' => 'datetime',
        'break_end_at' => 'datetime',
    ];

    public function stamp()
    {
        return $this->belongsTo(Stamp::class);
    }
}
