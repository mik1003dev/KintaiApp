<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StampRequestBreak;
use App\Models\StampCorrectionRequest;
use Carbon\Carbon;

class StampRequestBreakSeeder extends Seeder
{
    public function run()
    {
        $requests = StampCorrectionRequest::with('stamp')->get();

        StampRequestBreak::whereIn('stamp_correction_request_id', $requests->pluck('id'))->delete();

        foreach ($requests as $request) {
            $workDate = Carbon::parse($request->stamp->work_date);

            StampRequestBreak::create([
                'stamp_correction_request_id' => $request->id,
                'break_start_at' => $workDate->copy()->setTime(12, 5),
                'break_end_at' => $workDate->copy()->setTime(13, 0),
            ]);
        }
    }
}
