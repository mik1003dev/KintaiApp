<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StampBreak;
use App\Models\Stamp;
use App\Models\User;
use Carbon\Carbon;

class StampBreakSeeder extends Seeder
{
    public function run()
    {
        $userIds = User::whereIn('email', [
            'user@example.com',
            'taro@example.com',
            'hanako@example.com',
            'ichiro@example.com',
        ])->pluck('id');

        $stamps = Stamp::whereIn('user_id', $userIds)->get();

        StampBreak::whereIn('stamp_id', $stamps->pluck('id'))->delete();

        foreach ($stamps as $stamp) {
            $workDate = Carbon::parse($stamp->work_date);

            if ($stamp->status === 'on_break') {
                StampBreak::create([
                    'stamp_id' => $stamp->id,
                    'break_start_at' => $workDate->copy()->setTime(12, 0),
                    'break_end_at' => null,
                ]);
                continue;
            }

            if (!$stamp->clock_in_at || !$stamp->clock_out_at) {
                continue;
            }

            StampBreak::create([
                'stamp_id' => $stamp->id,
                'break_start_at' => $workDate->copy()->setTime(12, 0),
                'break_end_at' => $workDate->copy()->setTime(13, 0),
            ]);

            if ($workDate->day % 10 === 0) {
                StampBreak::create([
                    'stamp_id' => $stamp->id,
                    'break_start_at' => $workDate->copy()->setTime(15, 0),
                    'break_end_at' => $workDate->copy()->setTime(15, 15),
                ]);
            }
        }
    }
}
