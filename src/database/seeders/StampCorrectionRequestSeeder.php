<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StampCorrectionRequest;
use App\Models\Stamp;
use App\Models\User;
use Carbon\Carbon;

class StampCorrectionRequestSeeder extends Seeder
{
    public function run()
    {
        $user = User::where('email', 'user@example.com')->firstOrFail();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $today = Carbon::today();

        $pendingStamp = $this->findStamp($user->id, $today->copy()->startOfMonth()->addDays(9));
        $approvedStamp = $this->findStamp($user->id, $today->copy()->subMonth()->startOfMonth()->addDays(9));

        $this->createRequest(
            $pendingStamp,
            $user,
            null,
            'pending',
            '出勤時刻と休憩時間の修正申請',
            '08:55',
            '18:00',
            null
        );

        $this->createRequest(
            $approvedStamp,
            $user,
            $admin,
            'approved',
            '承認済みの修正申請',
            '09:00',
            '18:05',
            now()->subDays(3)
        );
    }

    private function findStamp($userId, Carbon $preferredDate)
    {
        $targetDate = $preferredDate->lessThanOrEqualTo(Carbon::today())
            ? $preferredDate
            : Carbon::today();

        return Stamp::where('user_id', $userId)
            ->whereDate('work_date', '<=', $targetDate->toDateString())
            ->whereNotNull('clock_out_at')
            ->orderByDesc('work_date')
            ->firstOrFail();
    }

    private function createRequest($stamp, $user, $admin, $status, $note, $clockIn, $clockOut, $approvedAt)
    {
        $workDate = Carbon::parse($stamp->work_date);

        return StampCorrectionRequest::updateOrCreate(
            [
                'stamp_id' => $stamp->id,
                'status' => $status,
            ],
            [
                'user_id' => $user->id,
                'requested_clock_in_at' => $workDate->copy()->setTimeFromTimeString($clockIn),
                'requested_clock_out_at' => $workDate->copy()->setTimeFromTimeString($clockOut),
                'requested_note' => $note,
                'approved_by' => $admin ? $admin->id : null,
                'approved_at' => $approvedAt,
            ]
        );
    }
}
